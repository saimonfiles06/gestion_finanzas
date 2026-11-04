/**
 * LoginView.jsx — the login page: validate → login(). It does NOT navigate:
 * when the session changes, App.jsx shows the main view.
 */
import { useEffect, useRef, useState } from 'react';
import Icon from '../components/Icon.jsx';
import { useAuth } from '../hooks/useAuth.js';
import { ApiError } from '../api/ApiError.js';
import { DEMO_CREDENTIALS } from '../config.js';

/** Validation: field → function that returns an error message or ''. */
const RULES = {
	email: (value) =>
		value.trim().length === 0
			? 'Enter your email.'
			: value.trim().length < 3
				? 'The email must have at least 3 characters.'
				: '',
	password: (value) =>
		value.length === 0
			? 'Enter your password.'
			: value.length < 8
				? 'The password must have at least 8 characters.'
				: '',
};

const EMPTY = { email: '', password: '' };

export default function LoginView() {
	const { login } = useAuth();

	// Controlled form: the state is the source of truth of the inputs.
	const [values, setValues] = useState(EMPTY);
	const [errors, setErrors] = useState(EMPTY);
	const [formError, setFormError] = useState('');
	const [busy, setBusy] = useState(false);

	// Refs: access to DOM elements (to focus or select them).
	const usernameRef = useRef(null);
	const passwordRef = useRef(null);
	const submitRef = useRef(null);

	// After a failed login, select the password (effects run after React draws the page).
	useEffect(() => {
		if (formError) {
			passwordRef.current.select();
		}
	}, [formError]);

	/** Shows/clears the error of one field. Returns true if the field is valid. */
	function validateField(name, value) {
		const message = RULES[name](value);
		setErrors((current) => ({ ...current, [name]: message }));
		return message === '';
	}

	function handleChange(event) {
		const { name, value } = event.target;
		setValues((current) => ({ ...current, [name]: value }));
		// Re-validate while typing once the field has an error
		if (errors[name]) {
			validateField(name, value);
		}
	}

	function handleBlur(event) {
		const { name, value } = event.target;
		if (value) {
			validateField(name, value);
		}
	}

	async function handleSubmit(event) {
		event.preventDefault(); // stop the browser from reloading the page
		setFormError('');

		const usernameOk = validateField('username', values.username);
		const passwordOk = validateField('password', values.password);
		if (!usernameOk) {
			usernameRef.current.focus();
			return;
		}
		if (!passwordOk) {
			passwordRef.current.focus();
			return;
		}

		setBusy(true);
		try {
			await login(values.username.trim(), values.password);
			// Nothing else: App.jsx sees the new session and shows the main view.
		} catch (error) {
			setBusy(false);
			if (error instanceof ApiError) {
				setFormError(error.status === 400 ? 'Wrong username or password.' : error.message);
			} else {
				// fetch() rejects with a TypeError when there is no connection
				setFormError('Cannot connect to the server. Check your internet connection.');
			}
		}
	}

	function fillDemoUser() {
		setValues({ ...DEMO_CREDENTIALS });
		setErrors(EMPTY);
		submitRef.current.focus();
	}

	return (
		<main className='login'>
			<section
				className='login__card'
				aria-labelledby='login-title'>
				<div className='login__logo'>
					<Icon
						name='logo'
						size='xl'
					/>
				</div>
				<h1
					id='login-title'
					className='login__title'>
					Sign in to Base App
				</h1>
				<p className='login__subtitle'>Use a DummyJSON user account.</p>

				<form
					className='login__form'
					noValidate
					onSubmit={handleSubmit}>
					<div className='field'>
						<label htmlFor='username'>Username</label>
						<input
							ref={usernameRef}
							id='username'
							name='username'
							type='text'
							autoComplete='username'
							autoCapitalize='none'
							spellCheck='false'
							autoFocus
							value={values.username}
							onChange={handleChange}
							onBlur={handleBlur}
							disabled={busy}
							aria-invalid={errors.username ? true : undefined}
							aria-describedby='username-error'
						/>
						<p
							id='username-error'
							className='field__error'>
							{errors.username}
						</p>
					</div>

					<div className='field'>
						<label htmlFor='password'>Password</label>
						<input
							ref={passwordRef}
							id='password'
							name='password'
							type='password'
							autoComplete='current-password'
							value={values.password}
							onChange={handleChange}
							onBlur={handleBlur}
							disabled={busy}
							aria-invalid={errors.password ? true : undefined}
							aria-describedby='password-error'
						/>
						<p
							id='password-error'
							className='field__error'>
							{errors.password}
						</p>
					</div>

					{formError && (
						<div
							className='form-error'
							role='alert'>
							<Icon name='alert' />
							<span>{formError}</span>
						</div>
					)}

					<button
						ref={submitRef}
						type='submit'
						className='btn btn--primary btn--block'
						disabled={busy}>
						{busy ? 'Signing in…' : 'Sign in'}
					</button>
				</form>

				<p className='login__hint'>
					Test user: <code>{DEMO_CREDENTIALS.username}</code> / <code>{DEMO_CREDENTIALS.password}</code>{' '}
					<button
						type='button'
						className='btn-link'
						onClick={fillDemoUser}>
						Fill in
					</button>
				</p>
			</section>
		</main>
	);
}
