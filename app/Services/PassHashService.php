<?php

namespace Dizzi\Services;

class PassHashService
{

    private static ?string $pepper = null;

    /**
     * Generate a hash from any key with password peppering support
     * @param string $key
     * any string to be hashed
     * @param null|string $pepper
     * [optional] a secret key that was used to spice up the hash to be generate, You are responsible for the security and confidentiality of the pepper, if you lose the pepper they will be irretrievable, think carefully before using this level of security in your hash
     * @return string
     * Return the generated hash
     */
    public static function passHash(string $key, ?string $pepper = null): string
    {
        self::$pepper = $pepper;
        if (self::isSetPepper()) {
            $spicyPassword = hash_hmac("sha256", $key, $pepper);
            return password_hash($spicyPassword, PASSWORD_DEFAULT);
        }
        return password_hash($key, PASSWORD_DEFAULT);
    }


    /**
     * checks if the keyword matches the hash entered
     * 
     * @param string $key
     * Keyword to be compared
     * @param string $hash
     * Hash to be compared with the keyword
     * @param null|string  $pepper
     * Optional - a secret key that was used to spice up the keyword to be compared, pass this parameter if you provided it when hashing the password previously
     */
    public static function compareHash(string $key, string $hash, ?string $pepper = null): bool
    {
        self::$pepper = $pepper;
        if (self::isSetPepper()) {
            $spicyPassword = hash_hmac("sha256", $key, $pepper);
            return password_verify($spicyPassword, $hash);
        }
        return password_verify($key, $hash);
    }

    /**
     * Check if the pepper is set.
     * 
     * @return bool
     * 
     * Return true if the pepper is set and false otherwise  
     */
    public static function isSetPepper(): bool
    {
        return isset(self::$pepper);
    }
}