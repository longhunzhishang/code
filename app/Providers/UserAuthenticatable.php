<?php
namespace App\Providers;
use Illuminate\Contracts\Auth\Authenticatable;
class UserAuthenticatable implements Authenticatable
{
    protected $user;
    public function __construct($user){
        $this->user = $user;
    }

    public function getAuthIdentifierName(){
    }
    public function getAuthIdentifier(){
    }
    public function getAuthPassword(){
    }
    public function getRememberToken(){
    }
    public function setRememberToken($value){
    }
    public function getRememberTokenName(){

    }
}