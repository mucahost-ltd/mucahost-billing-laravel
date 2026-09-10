import { Form, Head } from '@inertiajs/react';
import MailSettingController from '@/actions/App/Http/Controllers/Settings/MailSettingController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/mail-settings';

type MailSettings = {
    enabled: boolean;
    scheme: 'smtp' | 'smtps';
    host: string;
    port: number;
    username: string | null;
    password_set: boolean;
    from_address: string;
    from_name: string;
    environment_mailer: string;
};

export default function MailSettingsPage({
    settings,
}: {
    settings: MailSettings;
}) {
    return (
        <>
            <Head title="Email settings" />

            <h1 className="sr-only">Email settings</h1>

            <div className="space-y-10">
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Outgoing email"
                        description="Configure SMTP for ticket notifications and other system email"
                    />

                    <Form
                        {...MailSettingController.update.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess={['password']}
                        className="space-y-6"
                    >
                        {({ errors, processing }) => (
                            <>
                                <label className="flex items-start gap-3 rounded-lg border p-4">
                                    <input
                                        type="checkbox"
                                        name="enabled"
                                        value="1"
                                        defaultChecked={settings.enabled}
                                        className="mt-1 h-4 w-4"
                                    />
                                    <span>
                                        <span className="block text-sm font-medium">
                                            Use SMTP settings from the admin
                                            panel
                                        </span>
                                        <span className="text-muted-foreground block text-xs">
                                            When disabled, the application uses
                                            the{' '}
                                            <code>
                                                {settings.environment_mailer}
                                            </code>{' '}
                                            mailer configured in the
                                            environment.
                                        </span>
                                    </span>
                                </label>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="host">SMTP host</Label>
                                        <Input
                                            id="host"
                                            name="host"
                                            defaultValue={settings.host}
                                            required
                                            placeholder="smtp.example.com"
                                        />
                                        <InputError message={errors.host} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="port">Port</Label>
                                        <Input
                                            id="port"
                                            name="port"
                                            type="number"
                                            min="1"
                                            max="65535"
                                            defaultValue={settings.port}
                                            required
                                        />
                                        <InputError message={errors.port} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="scheme">
                                            Connection
                                        </Label>
                                        <select
                                            id="scheme"
                                            name="scheme"
                                            defaultValue={settings.scheme}
                                            className="bg-background h-9 rounded-md border px-3 text-sm"
                                        >
                                            <option value="smtp">
                                                SMTP / STARTTLS
                                            </option>
                                            <option value="smtps">
                                                SMTPS / implicit TLS
                                            </option>
                                        </select>
                                        <InputError message={errors.scheme} />
                                    </div>

                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="username">
                                            Username
                                        </Label>
                                        <Input
                                            id="username"
                                            name="username"
                                            defaultValue={
                                                settings.username ?? ''
                                            }
                                            autoComplete="username"
                                        />
                                        <InputError message={errors.username} />
                                    </div>

                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="password">
                                            Password
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            autoComplete="new-password"
                                            placeholder={
                                                settings.password_set
                                                    ? 'Saved — leave blank to keep it'
                                                    : 'SMTP password'
                                            }
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="from_address">
                                            From email
                                        </Label>
                                        <Input
                                            id="from_address"
                                            name="from_address"
                                            type="email"
                                            defaultValue={settings.from_address}
                                            required
                                        />
                                        <InputError
                                            message={errors.from_address}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="from_name">
                                            From name
                                        </Label>
                                        <Input
                                            id="from_name"
                                            name="from_name"
                                            defaultValue={settings.from_name}
                                            required
                                        />
                                        <InputError
                                            message={errors.from_name}
                                        />
                                    </div>
                                </div>

                                <Button disabled={processing}>
                                    Save email settings
                                </Button>
                            </>
                        )}
                    </Form>
                </div>

                <div className="space-y-6 border-t pt-8">
                    <Heading
                        variant="small"
                        title="Send a test email"
                        description="Save and enable the SMTP settings before testing delivery"
                    />

                    <Form
                        {...MailSettingController.sendTest.form()}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="test_email">
                                        Recipient
                                    </Label>
                                    <Input
                                        id="test_email"
                                        name="test_email"
                                        type="email"
                                        required
                                        placeholder="you@example.com"
                                    />
                                    <InputError message={errors.test_email} />
                                </div>

                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing || !settings.enabled}
                                >
                                    Send test email
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

MailSettingsPage.layout = {
    breadcrumbs: [
        {
            title: 'Email settings',
            href: edit(),
        },
    ],
};
