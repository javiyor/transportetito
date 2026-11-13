<?php

namespace App\Services\Mail;

use App\Models\ConfiguracionCorreo;
use Illuminate\Support\Facades\Config;

class MailConfigurationService
{
    public function loadAndApply(): ?ConfiguracionCorreo
    {
        $settings = ConfiguracionCorreo::query()->find(1);

        if ($settings) {
            $this->apply($settings);
        }

        return $settings;
    }

    public function apply(ConfiguracionCorreo $settings): void
    {
        Config::set([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $settings->scheme,
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => $settings->host,
            'mail.mailers.smtp.port' => $settings->port,
            'mail.mailers.smtp.username' => $settings->username,
            'mail.mailers.smtp.password' => $settings->password,
            'mail.from.address' => $settings->from_address,
            'mail.from.name' => $settings->from_name,
        ]);
    }
}
