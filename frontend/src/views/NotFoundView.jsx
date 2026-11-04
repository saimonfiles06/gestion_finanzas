/**
 * NotFoundView.jsx — page for unknown routes.
 */
import Icon from '../components/Icon.jsx';
import { useHashRoute } from '../router.js';
import { DEFAULT_ROUTE } from '../config.js';

export default function NotFoundView() {
	const hash = useHashRoute();

	return (
		<div className='empty-state'>
			<Icon
				name='alert'
				size='xl'
			/>
			<h1>Page not found</h1>
			<p>
				The address <code>{hash}</code> does not exist.
			</p>
			<a
				className='btn btn--primary'
				href={DEFAULT_ROUTE}>
				Go to Tasks
			</a>
		</div>
	);
}
