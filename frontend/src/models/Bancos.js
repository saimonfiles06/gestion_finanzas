/**
 * Bancos — the fields of a DummyJSON user that the app needs.
 */
export class Bancos {
	constructor({ id, user_id, nombre="",saldo_inicial="", fecha_inicial="",permite_ingresos,permite_gastos,eliminado=false,fecha_creacion}) {
		this.id = id;
		this.user_id = user_id;
		this.nombre = nombre;
		this.saldo_inicial = saldo_inicial;
		this.fecha_inicial = fecha_inicial;
		this.permite_ingresos = permite_ingresos;
		this.permite_gastos = permite_gastos;
		this.eliminado = eliminado;
	}

	

	/** Builds a User from the API JSON (extra fields are ignored). */
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

	/** Plain object, to save it in sessionStorage. */
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
