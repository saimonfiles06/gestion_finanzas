/**
 * User — the fields of a DummyJSON user that the app needs.
 */
export class MovimientosBancos {
    constructor({ id, banco_id, user_id, nombre = "", cantidad_objetivo = "", fecha_objetivo = "", saldo_actual = "", fecha_saldo = "", es_fondo_emergencia = "", configurado = "", }) {
        this.id = id;
        this.banco_id = banco_id;
        this.user_id = user_id;
        this.nombre = nombre;
        this.cantidad_objetivo = cantidad_objetivo;
        this.fecha_objetivo = fecha_objetivo;
        this.saldo_actual = saldo_actual;
        this.fecha_saldo = fecha_saldo;
        this.es_fondo_emergencia = es_fondo_emergencia;
        this.configurado = configurado;
    }



    /** Builds a User from the API JSON (extra fields are ignored). */
    static fromJSON(json) {
        return new Objetivos({
            id: json.id,
            banco_id: json.banco_id,
            user_id: json.user_id,
            nombre: json.nombre,
            cantidad_objetivo: json.cantidad_objetivo,
            fecha_objetivo: json.fecha_objetivo,
            saldo_actual: json.saldo_actual,
            fecha_saldo: json.fecha_saldo,
            es_fondo_emergencia:json.es_fondo_emergencia,
            configurado:json.configurado,
        });
    }

    /** Plain object, to save it in sessionStorage. */
    toJSON() {
        return {
            id: this.id,
            banco_id: this.banco_id,
            user_id:this.user_id,
            nombre:this.nombre,
            cantidad_objetivo:this.cantidad_objetivo,
            fecha_objetivo:this.fecha_objetivo, 
            saldo_actual:this.saldo_actual,
            fecha_saldo:this.fecha_saldo,
            es_fondo_emergencia:this.es_fondo_emergencia,
        };
    }
}
