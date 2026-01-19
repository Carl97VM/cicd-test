<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $persona_id
 * @property string $codigo
 * @property string $tipo_proveedor
 * @property string|null $rubro
 * @property float $limite_credito
 * @property float $credito_usado
 * @property int $dias_credito
 * @property float $descuento_general
 * @property string|null $cuenta_bancaria
 * @property string|null $banco
 * @property string|null $nombre_contacto
 * @property string|null $cargo_contacto
 * @property string|null $telefono_contacto
 * @property string|null $email_contacto
 * @property string|null $observaciones
 * @property \Illuminate\Support\Carbon $fecha_registro
 * @property \Illuminate\Support\Carbon|null $ultima_compra
 * @property float $total_compras
 * @property int $calificacion
 * @property bool $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *                                                       * -- Accessors --
 * @property-read float $credito_disponible
 * @property-read bool $tiene_credito_disponible
 * @property-read float $porcentaje_credito_usado
 * @property-read string $calificacion_texto
 * @property-read Persona $persona
 * * -- Propiedades de Reportes --
 * @property int|null $cantidad_compras
 */
class Proveedor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'persona_id',
        'codigo',
        'tipo_proveedor',
        'rubro',
        'limite_credito',
        'credito_usado',
        'dias_credito',
        'descuento_general',
        'cuenta_bancaria',
        'banco',
        'nombre_contacto',
        'cargo_contacto',
        'telefono_contacto',
        'email_contacto',
        'observaciones',
        'fecha_registro',
        'ultima_compra',
        'total_compras',
        'calificacion',
        'estado',
    ];

    protected $casts = [
        'limite_credito' => 'float',
        'credito_usado' => 'float',
        'dias_credito' => 'integer',
        'descuento_general' => 'float',
        'total_compras' => 'float',
        'calificacion' => 'integer',
        'fecha_registro' => 'date',
        'ultima_compra' => 'date',
        'estado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'credito_disponible',
        'tiene_credito_disponible',
        'porcentaje_credito_usado',
        'calificacion_texto',
    ];

    public const TIPO_PRODUCTO = 'Producto';

    public const TIPO_SERVICIO = 'Servicio';

    public const TIPO_AMBOS = 'Ambos';

    public const CODIGO_PREFIJO = 'PROV';

    public const CALIFICACION_MALO = 1;

    public const CALIFICACION_REGULAR = 2;

    public const CALIFICACION_BUENO = 3;

    public const CALIFICACION_EXCELENTE = 4;

    public const CALIFICACION_SOBRESALIENTE = 5;

    public const CALIFICACIONES = [
        self::CALIFICACION_MALO => 'Malo',
        self::CALIFICACION_REGULAR => 'Regular',
        self::CALIFICACION_BUENO => 'Bueno',
        self::CALIFICACION_EXCELENTE => 'Excelente',
        self::CALIFICACION_SOBRESALIENTE => 'Sobresaliente',
    ];

    /* --- RELACIONES --- */

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id');
    }

    /* --- SCOPES --- */

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', true);
    }

    public function scopeMejorCalificados(Builder $query): Builder
    {
        return $query->where('calificacion', '>=', 4);
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
        if ($this->limite_credito <= 0) {
            return 0.0;
        }

        return (float) round(($this->credito_usado / $this->limite_credito) * 100, 2);
    }

    public function getCalificacionTextoAttribute(): string
    {
        return self::CALIFICACIONES[$this->calificacion] ?? 'Sin calificar';
    }

    /* --- MÉTODOS ESTÁTICOS --- */

    public static function generarCodigo(): string
    {
        return DB::transaction(function () {
            /** @var Proveedor|null $ultimo */
            $ultimo = self::lockForUpdate()->orderBy('id', 'desc')->first();
            $numero = $ultimo ? (int) substr($ultimo->codigo, strlen(self::CODIGO_PREFIJO)) + 1 : 1;

            return self::CODIGO_PREFIJO.str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
        });
    }

    /* --- LÓGICA DE NEGOCIO --- */

    public function puedeComprarACredito(float $monto): bool
    {
        return $this->estado && $this->credito_disponible >= $monto && $this->dias_credito > 0;
    }

    public function usarCredito(float $monto): bool
    {
        if ($this->credito_usado + $monto > $this->limite_credito) {
            throw new \Exception('Monto excede el crédito disponible');
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

    public function getNombreCompleto(): string
    {
        return $this->persona->nombre_completo ?? 'Sin nombre';
    }
}
