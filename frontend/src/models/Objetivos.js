/**
 * Objetivos — objetivo de ahorro del usuario (tabla `objetivos`).
 */
export class Objetivos {
    constructor({
        id,
        user_id,
        nombre = "",
        cantidad_objetivo = "",
        fecha_objetivo = null, // nullable en la BD
        saldo_actual = "",
        fecha_saldo = "",
        es_fondo_emergencia = false,
        configurado = false,
    }) {
        this.id = id;
        this.user_id = user_id;
        this.nombre = nombre;
        this.cantidad_objetivo = cantidad_objetivo;
        this.fecha_objetivo = fecha_objetivo;
        this.saldo_actual = saldo_actual;
        this.fecha_saldo = fecha_saldo;
        this.es_fondo_emergencia = es_fondo_emergencia;
        this.configurado = configurado;
    }

    /** Construye un Objetivos desde el JSON de la API (ignora campos extra). */
    static fromJSON(json) {
        return new Objetivos({
            id: json.id,
            user_id: json.user_id,
            nombre: json.nombre,
            cantidad_objetivo: json.cantidad_objetivo,
            fecha_objetivo: json.fecha_objetivo,
            saldo_actual: json.saldo_actual,
            fecha_saldo: json.fecha_saldo,
            es_fondo_emergencia: json.es_fondo_emergencia,
            configurado: json.configurado,
        });
    }

    /** Objeto plano para guardar en sessionStorage o enviar a la API. */
    toJSON() {
        return {
            id: this.id,
            user_id: this.user_id,
            nombre: this.nombre,
            cantidad_objetivo: this.cantidad_objetivo,
            fecha_objetivo: this.fecha_objetivo,
            saldo_actual: this.saldo_actual,
            fecha_saldo: this.fecha_saldo,
            es_fondo_emergencia: this.es_fondo_emergencia,
            configurado: this.configurado,
        };
    }
}