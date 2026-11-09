/**
 * User — the fields of a DummyJSON user that the app needs.
 */
export class Registros {
    constructor({ id, banco_id, categoria_id, tipo = "", importe = "", fecha = "", descripcion }) {
        this.id = id;
        this.banco_id = banco_id;
        this.categoria_id = categoria_id;
        this.tipo = tipo;
        this.importe = importe;
        this.fecha = fecha;
        this.descripcion = descripcion;
    }



    /** Builds a User from the API JSON (extra fields are ignored). */
    static fromJSON(json) {
        return new Registros({
            id: json.id,
            banco_id: json.banco_id,
            categoria_id: json.categoria_id,
            tipo: json.tipo,
            importe: json.importe,
            fecha: json.fecha,
            descripcion: json.descripcion,
        });
    }

    /** Plain object, to save it in sessionStorage. */
    toJSON() {
        return {
            id: this.id,
            email: this.email,
            password: this.password,
            preferencia_modo: this.preferencia_modo,
            fecha_registro: this.fecha_registro,
        };
    }
}
