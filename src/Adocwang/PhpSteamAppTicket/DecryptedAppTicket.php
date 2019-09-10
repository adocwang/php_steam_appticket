<?php
/**
 * Created by PhpStorm.
 * User: wangyibo
 * Date: 2019/9/2
 * Time: 16:06
 */

namespace Adocwang\PhpSteamAppTicket;


class DecryptedAppTicket
{
    /**
     * @var string
     */
    public $steamID;

    /**
     * @var int
     */
    public $appID;

    /**
     * @var string
     */
    public $ownershipTicketExternalIP;

    /**
     * @var string
     */
    public $ownershipTicketInternalIP;

    /**
     * @var int
     */
    public $ownershipFlags;

    /**
     * @var string
     */
    public $ownershipTicketGenerated;

    /**
     * @var array array of int
     */
    public $licenses;

    /**
     * @var array array of {appId, licenses}
     */
    public $dlc;

    /**
     * @var string
     */
    public $userData;
}