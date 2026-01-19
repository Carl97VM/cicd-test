<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Modelo Cliente
 *
 * * @property int $id
 * @property int $persona_id
 * @property string $codigo
 * @property string $tipo_cliente
 * @property float $limite_credito
 * @property float $credito_usado
 * @property int $dias_credito
 * @property float $descuento_general
 * @property string|null $observaciones
 * @property \Illuminate\Support\Carbon $fecha_registro
 * @property \Illuminate\Support\Carbon|null $ultima_compra
 * @property float $total_compras
 * @property bool $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *                                                       * -- Accessors --
 * @property-read float $credito_disponible
 * @property-read bool $tiene_credito_disponible
 * @property-read float $porcentaje_credito_usado
 * * -- Relaciones --
 * @property-read Persona $persona
 * @property-read \Illuminate\Database\Eloquent\Collection|Venta[] $ventas
 * * -- Propiedades de Reportes (Evita errores en PHPStan) --
 * @property int|null $cantidad_compras
 */
class Cliente extends Model
{
    use HasFactory;

    protected $table = 'clientes';

    protected $fillable = [
        'persona_id',
        'codigo',
        'tipo_cliente',
        'limite_credito',
        'credito_usado',
        'dias_credito',
        'descuento_general',
        'observaciones',
        'fecha_registro',
        'ultima_compra',
        'total_compras',
        'estado',
    ];

    protected $casts = [
        'limite_credito' => 'float',
        'credito_usado' => 'float',
        'dias_credito' => 'integer',
        'descuento_general' => 'float',
        'total_compras' => 'float',
        'fecha_registro' => 'date',
        'ultima_compra' => 'date',
        'estado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'credito_disponible',
        'tiene_credito_disponible',
        'porcentaje_credito_usado',
    ];

    public const TIPO_REGULAR = 'Regular';

    public const TIPO_VIP = 'VIP';

    public const TIPO_CORPORATIVO = 'Corporativo';

    public const TIPO_MAYORISTA = 'Mayorista';

    public const CODIGO_PREFIJO = 'CLI';

    /* --- RELACIONES --- */

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }

    /* --- QUERY SCOPES --- */

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', true);
    }

    public function scopeVip(Builder $query): Builder
    {
        return $query->where('tipo_cliente', self::TIPO_VIP);
    }

    public function scopePorTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo_cliente', $tipo);
    }

    public function scopeConCredito(Builder $query): Builder
    {
        return $query->where('limite_credito', '>', 0)
            ->whereRaw('credito_usado < limite_credito');
    }

    public function scopeConPersona(Builder $query): Builder
    {
        return $query->with('persona');
    }

    /* --- ACCESSORS --- */

    public function getCreditoDisponibleAttribute(): float
    {
        return (float) max(0, $this->limite_credito - $this->credito_usado);
    }

    public function getTieneCreditoDisponibleAttribute(): bool
    {
        return $this->credito_disponible > 0 && $this->dias_credito > 0;
    }

    public function getPorcentajeCreditoUsadoAttribute(): float
    {
        if ($this->limite_credito == 0) {
            return 0.0;
        }

        return (float) round(($this->credito_usado / $this->limite_credito) * 100, 2);
    }

    /* --- MÉTODOS ESTÁTICOS --- */

    public static function generarCodigo(): string
    {
        return DB::transaction(function () {
            /** @var Cliente|null $ultimo */
            $ultimo = self::lockForUpdate()->orderBy('id', 'desc')->first();
            $numero = $ultimo ? (int) substr($ultimo->codigo, strlen(self::CODIGO_PREFIJO)) + 1 : 1;

            return self::CODIGO_PREFIJO.str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
        });
    }

    /* --- LÓGICA DE NEGOCIO --- */

    public function puedeComprarACredito(float $monto): bool
    {
        return $this->estado
            && $this->credito_disponible >= $monto
            && $this->dias_credito > 0;
    }

    public function usarCredito(float $monto): bool
    {
        if (($this->credito_usado + $monto) > $this->limite_credito) {
            throw new \Exception("El monto ({$monto}) excede el crédito disponible");
        }
        $this->credito_usado += $monto;

        return $this->save();
    }

    public function liberarCredito(float $monto): bool
    {
        $this->credito_usado = (float) max(0, $this->credito_usado - $monto);

        return $this->save();
    }

    public function actualizarUltimaCompra($fecha = null): bool
    {
        $this->ultima_compra = $fecha ?? now();

        return $this->save();
    }

    public function incrementarTotalCompras(float $monto): bool
    {
        $this->total_compras += $monto;

        return $this->save();
    }

    /* --- AUXILIARES --- */

    public function esVip(): bool
    {
        return $this->tipo_cliente === self::TIPO_VIP;
    }

    public function esMayorista(): bool
    {
        return $this->tipo_cliente === self::TIPO_MAYORISTA;
    }

    public function esCorporativo(): bool
    {
        return $this->tipo_cliente === self::TIPO_CORPORATIVO;
    }

    public function getNombreCompleto(): string
    {
        return $this->persona->nombre_completo ?? 'Sin nombre';
    }

    public function getNumeroDocumento(): string
    {
        return $this->persona->numero_documento ?? '';
    }
}
