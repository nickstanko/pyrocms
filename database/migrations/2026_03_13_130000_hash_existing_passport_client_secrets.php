<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;

class HashExistingPassportClientSecrets extends Migration
{
    public function up()
    {
        $clientModel = Passport::client();

        $clientModel->newQuery()->whereNotNull('secret')->each(function ($client) {
            if (Hash::isHashed($client->getRawOriginal('secret'))) {
                return;
            }

            $client->forceFill([
                'secret' => $client->getRawOriginal('secret'),
            ])->save();
        });
    }

    public function down()
    {
        //
    }
}
