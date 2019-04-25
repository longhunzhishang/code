<?php
namespace App\Providers;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
class MyUserProvider extends EloquentUserProvider
{
    public function __construct($hasher, $model){
        parent::__construct($hasher, $model);
    }
    private function userKey($str) {
        return '' === $str ? '' : md5(sha1($str) . 'oS1wcdz9ysuxalNhH5AXkWVC4vbFE7ZDYOfnMQPq');
    }
    public function validateCredentials(Authenticatable $user, array $credentials){
        $plain = $credentials['password'];
        $hashewdpwd = $this->userKey($plain);
        $pwd = $user->getAuthPassword();
        return $hashewdpwd ===  $pwd;
    }
}