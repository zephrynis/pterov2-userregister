import { definePterodactylExtension } from '@pterodactyl/sdk';

export function RegisterLink() {
    return (
        <p style={{ marginTop: '1.5rem', textAlign: 'center', color: 'var(--foreground)' }}>
            New here?{' '}
            <a href="/extensions/userregister" style={{ color: 'var(--foreground)', textDecoration: 'underline' }}>
                Create account
            </a>
        </p>
    );
}

export default definePterodactylExtension({
    setup({ config, slots }) {
        if (config.registration_open === true) slots.register('auth.login.form.after', RegisterLink);
    },
});
