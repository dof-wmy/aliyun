<?php
namespace AliyunMNS\Clients;

use AliyunMNS\Client;
use AliyunMNS\Exception\MnsException;
use AliyunMNS\Requests\SendMessageRequest;
use AliyunMNS\Requests\CreateQueueRequest;

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
        try {
            $this->client->createQueue($request);
            if(config('aliyun.mns.debug')) logger("AliyunMNS QueueCreated: {$queueName}");
        } catch (MnsException $e) {
            logger()->error("AliyunMNS CreateQueue Failed: {$queueName}", [
                'e' => $e,
            ]);
        }

    }
    function deleteQueue($queueName){
        try {
            $this->client->deleteQueue($queueName);
            if(config('aliyun.mns.debug')) logger("AliyunMNS DeleteQueue Succeed: {$queueName}");
        } catch (MnsException $e) {
            logger()->error("AliyunMNS DeleteQueue Failed: {$queueName}", [
                'e' => $e,
            ]);
        }
    }
    function sendMessage($queueName, $messageBody){
        $queue = $this->client->getQueueRef($queueName);
        // as the messageBody will be automatically encoded
        // the MD5 is calculated for the encoded body
        $bodyMD5 = md5(base64_encode($messageBody));
        $request = new SendMessageRequest($messageBody);
        try {
            $queue->sendMessage($request);
            if(config('aliyun.mns.debug')) logger("AliyunMNS MessageSent: {$queueName} => {$messageBody}");
        } catch (MnsException $e) {
            logger()->error("AliyunMNS SendMessage Failed: {$queueName} => {$messageBody}", [
                'e' => $e,
            ]);
        }
    }
    function receiveMessage($queueName){
        $queue = $this->client->getQueueRef($queueName);
        $receiptHandle = null;
        try {
            // when receiving messages, it's always a good practice to set the waitSeconds to be 30.
            // it means to send one http-long-polling request which lasts 30 seconds at most.
            $res = $queue->receiveMessage(30);
            if(config('aliyun.mns.debug')) logger("AliyunMNS ReceiveMessage Succeed: {$queueName}", [
                $res->getMessageBody()
            ]);
        } catch (MnsException $e) {
            logger()->error("AliyunMNS ReceiveMessage Failed: {$queueName}", [
                'e' => $e,
            ]);
        }
    }
//    function deleteMessage($queueName){
//        $queue = $this->client->getQueueRef($queueName);
//        $res = $queue->receiveMessage(30);
//        $receiptHandle = $res->getReceiptHandle();
//        try {
//            $res = $queue->deleteMessage($receiptHandle);
//            echo "DeleteMessage Succeed! \n";
//        } catch (MnsException $e) {
//            echo "DeleteMessage Failed: " . $e;
//            return;
//        }
//    }
}