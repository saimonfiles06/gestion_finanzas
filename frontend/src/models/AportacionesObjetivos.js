/**
 * AportacionesObjetivos — aportación de un banco a un objetivo (tabla `aportaciones_objetivos`).
 */
export class AportacionesObjetivos {
    constructor({ id, user_id, objetivo_id, banco_id, importe = "", fecha = "", descripcion = "" }) {
        this.id = id;
        this.user_id = user_id;
        this.objetivo_id = objetivo_id;
        this.banco_id = banco_id;
        this.importe = importe;
        this.fecha = fecha;
        this.descripcion = descripcion;
    }

    /** Construye una AportacionesObjetivos desde el JSON de la API (ignora campos extra). */
    static fromJSON(json) {
        return new AportacionesObjetivos({
            id: json.id,
            user_id: json.user_id,
            objetivo_id: json.objetivo_id,
            banco_id: json.banco_id,
            importe: json.importe,
            fecha: json.fecha,
            descripcion: json.descripcion, // antes: json.fecha
        });
    }

    /** Objeto plano para guardar en sessionStorage o enviar a la API. */
    toJSON() {
        return {
            id: this.id,
            user_id: this.user_id,
            objetivo_id: this.objetivo_id,
            banco_id: this.banco_id,
            importe: this.importe,
            fecha: this.fecha,
            descripcion: this.descripcion,
        };
    }
}