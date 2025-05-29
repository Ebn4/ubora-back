<?php

namespace App\Services;

interface UserLdapService
{

    public function searchUser(string $search);

}
