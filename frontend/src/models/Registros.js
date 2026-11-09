/**
 * Registros — ingreso o gasto de un banco (tabla `registros`).
 */
export class Registros {
    constructor({ id, user_id, banco_id, categoria_id, tipo = "", importe = "", fecha = "", descripcion = "" }) {
        this.id = id;
        this.user_id = user_id;
        this.banco_id = banco_id;
        this.categoria_id = categoria_id;
        this.tipo = tipo; // 'ingreso' | 'gasto'
        this.importe = importe;
        this.fecha = fecha;
        this.descripcion = descripcion;
    }

    /** Construye un Registros desde el JSON de la API (ignora campos extra). */
    static fromJSON(json) {
        return new Registros({
            id: json.id,
            user_id: json.user_id,
            banco_id: json.banco_id,
            categoria_id: json.categoria_id,
            tipo: json.tipo,
            importe: json.importe,
            fecha: json.fecha,
            descripcion: json.descripcion,
        });
    }

    /** Objeto plano para guardar en sessionStorage o enviar a la API. */
    toJSON() {
        return {
            id: this.id,
            user_id: this.user_id,
            banco_id: this.banco_id,
            categoria_id: this.categoria_id,
            tipo: this.tipo,
            importe: this.importe,
            fecha: this.fecha,
            descripcion: this.descripcion,
        };
    }
}