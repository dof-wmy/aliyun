<?php
namespace AliyunMNS\Clients;

use AliyunMNS\Client;
use AliyunMNS\Exception\MnsException;
use AliyunMNS\Requests\SendMessageRequest;
use AliyunMNS\Requests\CreateQueueRequest;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;

class Queue
{
    private $accessId;
    private $accessKey;
    private $endPoint;
    private $client;

    public function __construct($accessId = null, $accessKey = null, $endPoint = null)
    {
        $this->accessId = $accessId ?: config('aliyun.mns.accessId');
        $this->accessKey = $accessId ?: config('aliyun.mns.accessKey');
        $this->endPoint = $accessId ?: config('aliyun.mns.endPoint');
        $this->client = new Client($this->endPoint, $this->accessId, $this->accessKey);
    }

    function createQueue($queueName){
        $request = new CreateQueueRequest($queueName);
        $this->client->createQueue($request);
        if(config('aliyun.mns.debug')) logger("AliyunMNS QueueCreated: {$queueName}");
    }
    function deleteQueue($queueName){
        $this->client->deleteQueue($queueName);
        if(config('aliyun.mns.debug')) logger("AliyunMNS DeleteQueue Succeed: {$queueName}");
    }
    function sendMessage($queueName, $messageBody){
        $messageEncrypt = $this->encrypt($messageBody);
        $queue = $this->client->getQueueRef($queueName, false);
        // as the messageBody will be automatically encoded
        // the MD5 is calculated for the encoded body
        $bodyMD5 = md5(base64_encode($messageEncrypt));
        $request = new SendMessageRequest($messageEncrypt);
        $queue->sendMessage($request);
        if(config('aliyun.mns.debug')) logger("AliyunMNS MessageSent: {$queueName} => {$messageBody}");
    }
    function receiveMessage($queueName){
        $queue = $this->client->getQueueRef($queueName, false);
        $receiptHandle = null;
        try {
            // when receiving messages, it's always a good practice to set the waitSeconds to be 30.
            // it means to send one http-long-polling request which lasts 30 seconds at most.
            $res = $queue->receiveMessage(30);
            $messageEncrypt = $res->getMessageBody();
            if(empty($messageEncrypt)){
                return null;
            }
            $messageBody = $this->decrypt($messageEncrypt);
            if(config('aliyun.mns.debug')) logger("AliyunMNS ReceiveMessage Succeed: {$queueName}", [
                $messageBody,
            ]);
            return [
                'messageBody' => $messageBody,
                'receiptHandle' => $res->getReceiptHandle(),
            ];
        } catch (MnsException $e) {
            if($e->getCode() !== 404){
                logger()->error("AliyunMNS ReceiveMessage Failed: {$queueName}", [
                    'e' => $e,
                ]);
                throw new MnsException($e->getCode(), $e->getMessage());
            }
        }
    }
    function deleteMessage($queueName, $receiptHandle){
        $queue = $this->client->getQueueRef($queueName);
        $res = $queue->deleteMessage($receiptHandle);
        if(config('aliyun.mns.debug')) logger("AliyunMNS DeleteMessage Succeed! : {$queueName}", [
            $receiptHandle
        ]);
    }

    function encrypt($str){
        $Encrypter = new Encrypter($this->getKey(), config('app.cipher'));
        $messageEncrypt = $Encrypter->encrypt($str, false);
        return $messageEncrypt;
    }

    function decrypt($messageEncrypt){
        $Encrypter = new Encrypter($this->getKey(), config('app.cipher'));
        $messageBody = $Encrypter->decrypt($messageEncrypt, false);
        return $messageBody;
    }

    function getKey(){
        $key = '';
        if (Str::startsWith($key = config('aliyun.mns.key'), 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }
        return $key;
    }
}
