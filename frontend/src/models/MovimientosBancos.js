/**
 * User — the fields of a DummyJSON user that the app needs.
 */
export class MovimientosBancos {
    constructor({ id, banco_id, registro_id, tipo = "", importe = "", fecha = "", descripcion = "" }) {
        this.id = id;
        this.banco_id = banco_id;
        this.registro_id = registro_id;
        this.tipo=tipo;
        this.importe = importe;
        this.fecha = fecha;
        this.descripcion = descripcion;
    }



    /** Builds a User from the API JSON (extra fields are ignored). */
    static fromJSON(json) {
        return new MovimientosBancos({
            id: json.id,
            banco_id: json.banco_id,
            registro_id: json.registro_id,
            tipo:json.tipo,
            importe: json.importe,
            fecha: json.fecha,
            descripcion: json.descripcion,
        });
    }

    /** Plain object, to save it in sessionStorage. */
    toJSON() {
        return {
            id: this.id,
            banco_id: this.banco_id,
            registro_id: this.registro_id,
            tipo:this.tipo,
            importe: this.importe,
            fecha: this.fecha,
            descripcion: this.descripcion,
        };
    }
}
