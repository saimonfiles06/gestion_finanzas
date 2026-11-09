/**
 * User — the fields of a DummyJSON user that the app needs.
 */
export class AportacionesObjetivos {
    constructor({ id, user_id,banco_id,importe="",fecha="",descripcion=""}) {
        this.id = id;
        this.user_id = user_id;
        this.banco_id = banco_id;
        this.importe=importe;
        this.fecha=fecha;
        this.descripcion=descripcion;
    }



    /** Builds a User from the API JSON (extra fields are ignored). */
    static fromJSON(json) {
        return new AportacionesObjetivos({
            id: json.id,
            user_id: json.user_id,
            banco_id: json.banco_id,
            importe:json.importe,
            fecha:json.fecha,
            descripcion:json.fecha,
        });
    }

    /** Plain object, to save it in sessionStorage. */
    toJSON() {
        return {
            id: this.id,
            user_id: this.user_id,
            banco_id: this.banco_id,
            importe:this.importe,
            fecha:this.fecha,
            descripcion:this.descripcion,
        };
    }
}
