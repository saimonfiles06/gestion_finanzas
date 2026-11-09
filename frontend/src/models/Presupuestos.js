export class Presupuestos {
    constructor({ id, user_id, categoria_id, cantidad_limite = "", periodo = "" }) {
        this.id = id;
        this.user_id = user_id;
        this.categoria_id = categoria_id;
        this.cantidad_limite = cantidad_limite;
        this.periodo = periodo;
    }



    static fromJSON(json) {
        return new Presupuestos({
            id: json.id,
            user_id: json.user_id,
            categoria_id: json.categoria_id,
            cantidad_limite: json.cantidad_limite,
            periodo: json.periodo,
        });
    }

    /** Plain object, to save it in sessionStorage. */
    toJSON() {
        return {
            id: this.id,
            user_id: this.user_id,
            categoria_id: this.categoria_id,
            cantidad_limite: this.cantidad_limite,
            periodo: this.periodo,
        };
    }
}
