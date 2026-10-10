import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';

vi.mock('@pterodactyl/sdk', () => ({ definePterodactylExtension: (definition: unknown) => definition }));
import extension, { RegisterLink } from '../src/client';

afterEach(cleanup);

describe('login registration link', () => {
    it('links to the extension web route without intercepting login submissions', () => {
        render(<RegisterLink />);
        expect(screen.getByRole('link', { name: 'Create account' }).getAttribute('href')).toBe('/extensions/userregister');
        expect(screen.queryByRole('button')).toBeNull();
    });

    it('only contributes to login when explicitly enabled for guests', () => {
        for (const value of [undefined, false, 'true', 1, null]) {
            const register = vi.fn();
            extension.setup({ config: { registration_open: value }, slots: { register } } as never);
            expect(register).not.toHaveBeenCalled();
        }
        const register = vi.fn();
        extension.setup({ config: { registration_open: true }, slots: { register } } as never);
        expect(register).toHaveBeenCalledWith('auth.login.form.after', RegisterLink);
    });
});
