<?php

/**
 * Created by PhpStorm.
 * User: wangyibo
 * Date: 2019/9/2
 * Time: 16:03
 */

namespace Adocwang\PhpSteamAppTicket;

class SteamEncryptedAppTicket
{
    /**
     * @param $ticket string hax string of steam encrypted app ticket
     * @param $decryptionKey string hax string of steam sdk key to decrypted app ticket
     * @return DecryptedAppTicket
     * @throws \Exception
     */
    public static function parse($ticket, $decryptionKey)
    {
        $matched = preg_match('/^([0-9]|[a-f])+$/i', $ticket, $match);
        if ($matched != 1) {
            throw new \Exception('illegal ticket, ticket must be hex string.');
        }
        $ticket = hex2bin($match[0]);
        $outer = new EncryptedAppTicket();
        $outer->mergeFromString($ticket);
        $crcEncryptedticket = $outer->getCrcEncryptedticket();
        $crcEncryptedticket &= 0xFFFFFFFF;
        $decrypted = self::symmetricDecrypt($outer->getEncryptedTicket(), $decryptionKey);
        if ($crcEncryptedticket != $decrypted->crc32()) {
            return null;
        }
        $cbEncrypteduserdata = $outer->getCbEncrypteduserdata();
        $cbEncrypteduserdata &= 0xFFFFFFFF;
        $userData = $decrypted->slice(0, $cbEncrypteduserdata);
        $ownershipTicketLength = $decrypted->readUInt32LE($cbEncrypteduserdata);
        $ownershipTicket = self::parseAppTicket($decrypted->slice($cbEncrypteduserdata, $cbEncrypteduserdata + $ownershipTicketLength), true);
        $decryptedAppTicket = new DecryptedAppTicket();
        $decryptedAppTicket->appID = $ownershipTicket['appID'];
        $decryptedAppTicket->dlc = $ownershipTicket['dlc'];
        $decryptedAppTicket->licenses = $ownershipTicket['licenses'];
        $decryptedAppTicket->ownershipFlags = $ownershipTicket['ownershipFlags'];
        $decryptedAppTicket->ownershipTicketExternalIP = $ownershipTicket['ownershipTicketExternalIP'];
        $decryptedAppTicket->ownershipTicketGenerated = $ownershipTicket['ownershipTicketGenerated'];
        $decryptedAppTicket->ownershipTicketInternalIP = $ownershipTicket['ownershipTicketInternalIP'];
        $decryptedAppTicket->steamID = $ownershipTicket['steamID'];
        $decryptedAppTicket->userData = $userData;
        return $decryptedAppTicket;
    }

    public static function symmetricDecrypt($input, $key)
    {
        $key = hex2bin($key);
        $viInput = substr($input, 0, 16);
        $iv = openssl_decrypt(base64_encode($viInput), 'aes-256-ecb', $key, 2);
        $dataInput = substr($input, 16);
        $aesData = openssl_decrypt(base64_encode($dataInput), 'aes-256-cbc', $key, 0, $iv);
        return new Buffer($aesData);
    }

    /**
     * @param $ticket Buffer
     * @param bool $allowInvalidSignature
     * @return array|null
     */
    public static function parseAppTicket($ticket, $allowInvalidSignature = false)
    {
        $details = [];
        try {
            $initialLength = $ticket->readUInt32LE();
            if ($initialLength == 20) {
                // This is a full appticket, with a GC token and session header (in addition to ownership ticket)
                $details['authTicket'] = $ticket->slice($ticket->offset - 4, $ticket->offset - 4 + 52)->binString;
                $details['gcToken'] = $ticket->readUInt64LE();
                //details.steamID = new SteamID(ticket.readUint64().toString());
                $ticket->skip(8); // the SteamID gets read later on
                $details['tokenGenerated'] = $ticket->readUInt32LE();
                if ($ticket->readUInt32LE() != 24) {
                    // SESSIONHEADER should be 24 bytes.
                    return null;
                }
                $ticket->skip(8); // unknown 1 and unknown 2
                $details['sessionExternalIP'] = long2ip($ticket->readUInt32LE());
                $ticket->skip(8); // filter
                $details['clientConnectionTime'] = $ticket->readUInt32LE(); // time the client has been connected to Steam in ms
                $details['clientConnectionCount'] = $ticket->readUInt32LE(); // how many servers the client has connected to

                if ($ticket->readUInt32LE() + $ticket->offset != $ticket->limit) {
                    // OWNERSHIPSECTIONWITHSIGNATURE sectlength
                    return null;
                }
            } else {
                $ticket->skip(-4);
            }

            // Start reading the ownership ticket
            $ownershipTicketOffset = $ticket->offset;
            $ownershipTicketLength = $ticket->readUInt32LE(); // including itself, for some reason
            if ($ownershipTicketOffset + $ownershipTicketLength != $ticket->limit && $ownershipTicketOffset + $ownershipTicketLength + 128 != $ticket->limit) {
                return null;
            }

            $details['version'] = $ticket->readUInt32LE();
            $details['steamID'] = new SteamID($ticket->readUInt64LE() . "");
            $details['appID'] = $ticket->readUInt32LE();
            $details['ownershipTicketExternalIP'] = long2ip($ticket->readUInt32LE());
            $details['ownershipTicketInternalIP'] = long2ip($ticket->readUInt32LE());
            $details['ownershipFlags'] = $ticket->readUInt32LE();
            $details['ownershipTicketGenerated'] = $ticket->readUInt32LE();
            $details['ownershipTicketExpires'] = $ticket->readUInt32LE();

            $details['licenses'] = [];
            $licenseCount = $ticket->readUInt16LE();
            for ($i = 0; $i < $licenseCount; $i++) {
                $details['licenses'][] = $ticket->readUInt32LE();
            }

            $details['dlc'] = [];
            $dlcCount = $ticket->readUInt16LE();
            for ($i = 0; $i < $dlcCount; $i++) {
                $dlc = [];
                $dlc['appID'] = $ticket->readUInt32LE();
                $dlc['licenses'] = [];

                $licenseCount = $ticket->readUInt16LE();

                for ($j = 0; $j < $licenseCount; $j++) {
                    $dlc['licenses'][] = $ticket->readUInt32LE();
                }
                $details['dlc'][] = $dlc;
            }

            $ticket->readUInt16LE(); // reserved
            if ($ticket->offset + 128 == $ticket->limit) {
                // Has signature
                $details['signature'] = $ticket->slice($ticket->offset, $ticket->offset + 128)->binString;
                $pubKey = '-----BEGIN PUBLIC KEY-----
MIGdMA0GCSqGSIb3DQEBAQUAA4GLADCBhwKBgQDf7BrWLBBmLBc1OhSwfFkRf53T
2Ct64+AVzRkeRuh7h3SiGEYxqQMUeYKO6UWiSRKpI2hzic9pobFhRr3Bvr/WARvY
gdTckPv+T1JzZsuVcNfFjrocejN1oWI0Rrtgt4Bo+hOneoo3S57G9F1fOpn5nsQ6
6WOiu4gZKODnFMBCiQIBEQ==
-----END PUBLIC KEY-----';
                $verified = openssl_verify($ticket->slice($ownershipTicketOffset, $ownershipTicketOffset + $ownershipTicketLength)->binString, $details['signature'], $pubKey);
                $details['hasValidSignature'] = $verified;
            } else {
                $details['hasValidSignature'] = false;
            }
            $details['isExpired'] = $details['ownershipTicketExpires'] < time();
            $details['isValid'] = !$details['isExpired'] && (!isset($details['signature']) || $details['hasValidSignature']);
            if (!$details['hasValidSignature'] && !$allowInvalidSignature) {
                throw new \Exception("Missing or invalid signature");
            }
            return $details;
        } catch (\Exception $e) {
            if ($allowInvalidSignature) {
                return $details;
            }
            return null;
        }
    }
}