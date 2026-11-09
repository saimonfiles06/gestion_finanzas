/**
 * User — usuario de la aplicación (tabla `users`).
 * El backend oculta `password`, así que la API nunca lo devuelve.
 */
export class User {
    constructor({ id, email = "", preferencia_modo = "claro" }) {
        this.id = id;
        this.email = email;
        this.preferencia_modo = preferencia_modo; // 'claro' | 'oscuro'
    }

    /** Construye un User desde el JSON de la API (ignora campos extra). */
    static fromJSON(json) {
        return new User({
            id: json.id,
            email: json.email,
            preferencia_modo: json.preferencia_modo,
        });
    }

    /** Objeto plano para guardar en sessionStorage. */
    toJSON() {
        return {
            id: this.id,
            email: this.email,
            preferencia_modo: this.preferencia_modo,
        };
    }
}