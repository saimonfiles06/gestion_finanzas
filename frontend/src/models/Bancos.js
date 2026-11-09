/**
 * Bancos — cuenta bancaria del usuario (tabla `bancos`).
 */
export class Bancos {
    constructor({
        id,
        user_id,
        nombre = "",
        saldo_inicial = "",
        fecha_inicial = "",
        permite_ingresos = true, // default true en la BD
        permite_gastos = true,   // default true en la BD
        eliminado = false,
    }) {
        this.id = id;
        this.user_id = user_id;
        this.nombre = nombre;
        this.saldo_inicial = saldo_inicial;
        this.fecha_inicial = fecha_inicial;
        this.permite_ingresos = permite_ingresos;
        this.permite_gastos = permite_gastos;
        this.eliminado = eliminado;
    }

    /** Construye un Bancos desde el JSON de la API (ignora campos extra). */
    static fromJSON(json) {
        return new Bancos({
            id: json.id,
            user_id: json.user_id,
            nombre: json.nombre,
            saldo_inicial: json.saldo_inicial,
            fecha_inicial: json.fecha_inicial,
            permite_ingresos: json.permite_ingresos,
            permite_gastos: json.permite_gastos,
            eliminado: json.eliminado,
        });
    }

    /** Objeto plano para guardar en sessionStorage o enviar a la API. */
    toJSON() {
        return {
            id: this.id,
            user_id: this.user_id,
            nombre: this.nombre,
            saldo_inicial: this.saldo_inicial,
            fecha_inicial: this.fecha_inicial,
            permite_ingresos: this.permite_ingresos,
            permite_gastos: this.permite_gastos,
            eliminado: this.eliminado,
        };
    }
}