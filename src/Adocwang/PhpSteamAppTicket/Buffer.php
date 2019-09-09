<?php
/**
 * Created by PhpStorm.
 * User: wangyibo
 * Date: 2019/9/9
 * Time: 15:50
 */

namespace Adocwang\PhpSteamAppTicket;


class Buffer
{
    public $binString;
    public $offset = 0;
    public $limit = 0;

    function __construct($binString)
    {
        $this->binString = $binString;
        $this->limit = strlen($binString);
    }

    public function readUInt16LE($offset = null)
    {
        if ($offset == null) {
            $offset = $this->offset;
        }
        $dataLength = 2;
        $target = substr($this->binString, $offset, $dataLength);
        $res = unpack("v", $target);
        $this->skip($dataLength);
        if ($res) {
            return $res[1];
        }
    }

    public function readUInt32LE($offset = null)
    {
        if ($offset == null) {
            $offset = $this->offset;
        }
        $dataLength = 4;
        $target = substr($this->binString, $offset, $dataLength);
        $res = unpack("V", $target);
        $this->skip($dataLength);
        if ($res) {
            return $res[1];
        }
    }

    public function readUInt64LE($offset = null)
    {
        if ($offset == null) {
            $offset = $this->offset;
        }
        $dataLength = 8;
        $target = substr($this->binString, $offset, $dataLength);
        $res = unpack("P", $target);
        $this->skip($dataLength);
        if ($res) {
            return $res[1];
        }
    }

    public function skip($length)
    {
        $this->offset += $length;
    }

    public function toHexString()
    {
        return bin2hex($this->binString);
    }

    public function slice($start = 0, $end = null)
    {
        $length = null;
        if ($end > 0) {
            $length = $end - $start;
        } else if ($end < 0) {
            $length = $end;
        }
        $resStr = substr($this->binString, $start, $length);
        return new Buffer($resStr);
    }

    public function crc32()
    {
        return crc32($this->binString);
    }
}