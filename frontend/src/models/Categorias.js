
export class Categorias {
    constructor({ id, user_id, nombre="",tipo=""}) {
        this.id = id;
        this.user_id = user_id;
        this.nombre=nombre;
        this.tipo=tipo;

    }



    static fromJSON(json) {
        return new Categorias({
            id: json.id,
            user_id: json.user_id,
            nombre:json.nombre,
            tipo:json.tipo,
        });
    }

    /** Plain object, to save it in sessionStorage. */
    toJSON() {
        return {
            id: this.id,
            user_id: this.user_id,
            nombre:this.nombre,
            tipo:this.tipo,
        };
    }
}
