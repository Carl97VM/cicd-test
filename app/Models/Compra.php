<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $proveedor_id
 * @property string $codigo
 * @property string $tipo_compra
 * @property string $tipo_comprobante
 * @property string|null $numero_comprobante
 * @property \Illuminate\Support\Carbon $fecha_compra
 * @property \Illuminate\Support\Carbon|null $fecha_vencimiento
 * @property float $subtotal
 * @property float $porcentaje_impuesto
 * @property float $impuesto
 * @property float $porcentaje_descuento
 * @property float $descuento
 * @property float $total
 * @property string $estado
 * @property string|null $observaciones
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *                                                       * -- Accessors --
 * @property-read float $subtotal_calculado
 * @property-read int $cantidad_items
 * @property-read bool $es_credito
 * @property-read bool $puede_editarse
 * * -- Relaciones --
 * @property-read Proveedor $proveedor
 * @property-read \Illuminate\Database\Eloquent\Collection|DetalleCompra[] $detalles
 */
class Compra extends Model
{
    use HasFactory;

    protected $table = 'compras';

    /** @var list<string> */
    protected $fillable = [
        'proveedor_id',
        'codigo',
        'tipo_compra',
        'tipo_comprobante',
        'numero_comprobante',
        'fecha_compra',
        'fecha_vencimiento',
        'subtotal',
        'porcentaje_impuesto',
        'impuesto',
        'porcentaje_descuento',
        'descuento',
        'total',
        'estado',
        'observaciones',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fecha_compra' => 'date',
        'fecha_vencimiento' => 'date',
        'subtotal' => 'float',
        'porcentaje_impuesto' => 'float',
        'impuesto' => 'float',
        'porcentaje_descuento' => 'float',
        'descuento' => 'float',
        'total' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /** @var list<string> */
    protected $appends = ['cantidad_items', 'es_credito', 'puede_editarse'];

    public const TIPO_CONTADO = 'Contado';

    public const TIPO_CREDITO = 'Credito';

    public const COMPROBANTE_FACTURA = 'Factura';

    public const ESTADO_PENDIENTE = 'Pendiente';

    public const ESTADO_COMPLETADA = 'Completada';

    public const ESTADO_ANULADA = 'Anulada';

    public const CODIGO_PREFIJO = 'COMP';

    /* --- RELACIONES --- */

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleCompra::class, 'compra_id');
    }

    /* --- SCOPES --- */

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function scopeCompletadas(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_COMPLETADA);
    }

    public function scopeAnuladas(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ANULADA);
    }

    public function scopePorProveedor(Builder $query, int $proveedorId): Builder
    {
        return $query->where('proveedor_id', $proveedorId);
    }

    public function scopeEntreFechas(Builder $query, string $desde, string $hasta): Builder
    {
        return $query->whereBetween('fecha_compra', [$desde, $hasta]);
    }

    public function scopeConRelaciones(Builder $query): Builder
    {
        return $query->with(['proveedor.persona', 'detalles.producto']);
    }

    /* --- ACCESSORS --- */

    public function getCantidadItemsAttribute(): int
    {
        return (int) $this->detalles()->count();
    }

    public function getEsCreditoAttribute(): bool
    {
        return $this->tipo_compra === self::TIPO_CREDITO;
    }

    public function getPuedeEditarseAttribute(): bool
    {
        return $this->estado === self::ESTADO_PENDIENTE;
    }

    /* --- MÉTODOS ESTÁTICOS --- */

    public static function generarCodigo(): string
    {
        return DB::transaction(function () {
            /** @var Compra|null $ultimo */
            $ultimo = self::lockForUpdate()->orderBy('id', 'desc')->first();
            $numero = $ultimo ? (int) substr($ultimo->codigo, strlen(self::CODIGO_PREFIJO)) + 1 : 1;

            return self::CODIGO_PREFIJO.str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
        });
    }

    /* --- LÓGICA DE NEGOCIO --- */

    public function calcularTotales(): bool
    {
        $this->load('detalles');
        $subtotal = (float) $this->detalles->sum('subtotal');
        $descuento = $subtotal * ($this->porcentaje_descuento / 100);
        $baseImponible = $subtotal - $descuento;
        $impuesto = $baseImponible * ($this->porcentaje_impuesto / 100);
        $total = $baseImponible + $impuesto;

        $this->subtotal = (float) round($subtotal, 2);
        $this->descuento = (float) round($descuento, 2);
        $this->impuesto = (float) round($impuesto, 2);
        $this->total = (float) round($total, 2);

        return $this->save();
    }

    public function completar(): bool
    {
        if ($this->estado !== self::ESTADO_PENDIENTE) {
            throw new \Exception('Solo las compras pendientes pueden completarse');
        }

        return DB::transaction(function () {
            foreach ($this->detalles as $detalle) {
                /** @var Producto $producto */
                $producto = $detalle->producto;
                $producto->stock += $detalle->cantidad;
                $producto->save();
            }

            if ($this->es_credito) {
                $this->proveedor->usarCredito($this->total);
            }

            $this->proveedor->actualizarUltimaCompra($this->fecha_compra);
            $this->proveedor->incrementarTotalCompras($this->total);
            $this->estado = self::ESTADO_COMPLETADA;

            return $this->save();
        });
    }

    public function anular(): bool
    {
        if ($this->estado === self::ESTADO_ANULADA) {
            throw new \Exception('La compra ya está anulada');
        }

        return DB::transaction(function () {
            if ($this->estado === self::ESTADO_COMPLETADA) {
                foreach ($this->detalles as $detalle) {
                    /** @var Producto $producto */
                    $producto = $detalle->producto;
                    $producto->stock -= $detalle->cantidad;
                    $producto->save();
                }

                if ($this->es_credito) {
                    $this->proveedor->liberarCredito($this->total);
                }
            }

            $this->estado = self::ESTADO_ANULADA;

            return $this->save();
        });
    }

    public function estaVencida(): bool
    {
        if (! $this->es_credito || ! $this->fecha_vencimiento) {
            return false;
        }

        return $this->fecha_vencimiento->isPast();
    }

    public function diasParaVencimiento(): ?int
    {
        if (! $this->es_credito || ! $this->fecha_vencimiento) {
            return null;
        }

        // CORRECCIÓN: Forzamos el cast a int para cumplir con la firma del método
        return (int) now()->diffInDays($this->fecha_vencimiento, false);
    }
}
