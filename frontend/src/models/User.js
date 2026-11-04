/**
 * User — the fields of a DummyJSON user that the app needs.
 */
export class User {
    // El uso de variable='' en los parámetros del constructor es para que si no se pasa un valor,
    //  se asigne una cadena vacía por defecto.
	constructor({ id, email='',password='', preferencia_modo='',fecha_registro}) {
		this.id = id;
		this.email = email;
		this.password = password;
		this.preferencia_modo = preferencia_modo;
		this.fecha_registro = fecha_registro;
	}

	/** "Emily Johnson"*/
	get fullName() {
		return `${this.firstName} ${this.lastName}`.trim();
	}

	/** Builds a User from the API JSON (extra fields are ignored). */
	static fromJSON(json) {
		return new User({
			id: json.id,
			email: json.email,
			password: json.password,
			preferencia_modo: json.preferencia_modo,
			fecha_registro: json.fecha_registro,
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
