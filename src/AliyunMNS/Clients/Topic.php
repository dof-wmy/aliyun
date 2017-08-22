<?php
namespace AliyunMNS\Clients;

use AliyunMNS\Client;
use AliyunMNS\Model\SubscriptionAttributes;
use AliyunMNS\Requests\PublishMessageRequest;
use AliyunMNS\Requests\CreateTopicRequest;
use AliyunMNS\Requests\ListTopicRequest;
use AliyunMNS\Exception\MnsException;

class Topic
{
    private $accessId;
    private $accessKey;
    private $endPoint;
    private $ip;
    private $port;
    private $client;

    public function __construct($accessId = null, $accessKey = null, $endPoint = null, $ip = null, $port = 8000)
    {
        $this->accessId = $accessId ?: config('aliyun.mns.accessId');
        $this->accessKey = $accessId ?: config('aliyun.mns.accessKey');
        $this->endPoint = $accessId ?: config('aliyun.mns.endPoint');
        $this->ip = $accessId ?: config('aliyun.mns.ip');;
        $this->port = strval($accessId ?: config('aliyun.mns.port'));
        $this->client = new Client($this->endPoint, $this->accessId, $this->accessKey);
    }

    function createTopic($topicName){
        $request = new CreateTopicRequest($topicName);
        try{
            $this->client->createTopic($request);
            if(config('aliyun.mns.debug')) logger("AliyunMNS TopicCreated: {$topicName}");
            return true;
        }catch (MnsException $e){
            logger()->error("AliyunMNS CreateTopic Failed: ", [
                'e' => $e,
            ]);
        }
    }
    function deleteTopic($topicName){
        try {
            $res = $this->client->deleteTopic($topicName);
            if(config('aliyun.mns.debug')) logger("AliyunMNS DeleteTopic Succeed: {$topicName}");
            return true;
        }catch (MnsException $e) {
            logger()->error("AliyunMNS DeleteTopic Failed: ", [
                'e' => $e,
            ]);
        }
    }
    function listTopic(){
        $request = new ListTopicRequest();
        try {
            $listTopicResponse = $this->client->listTopic($request);
            $topicNames = $listTopicResponse->getTopicNames();
            if(config('aliyun.mns.debug')) logger("AliyunMNS listTopic Succeed: ", [
                'topicNames' => $topicNames,
            ]);
//            $listTopicResponse->isFinished();
            return $topicNames;
        } catch (MnsException $e) {
            logger()->error("AliyunMNS ListTopic Failed: ", [
                'e' => $e,
            ]);
        }
    }
    function subscribe($topicName, $subscriptionName){
        $topic = $this->client->getTopicRef($topicName);
        $attributes = new SubscriptionAttributes($subscriptionName, 'http://' . $this->ip . ':' . $this->port);
        try {
            $topic->subscribe($attributes);
            if(config('aliyun.mns.debug')) logger("Subscribed: {$topicName} => {$subscriptionName}");
        } catch (MnsException $e) {
            logger()->error("SubscribeFailed: ", [
                'e' => $e,
            ]);
        }
    }
    function unsubscribe($topicName, $subscriptionName){
        $topic = $this->client->getTopicRef($topicName);
//        sleep(20);
        try{
            $topic->unsubscribe($subscriptionName);
            if(config('aliyun.mns.debug')) logger("Unsubscribe Succeed: {$topicName} => {$subscriptionName}");
        } catch (MnsException $e) {
            logger()->error("Unsubscribe Failed: ", [
                'e' => $e,
            ]);
        }
    }
    function sendMessage($topicName, $messageBody){
        $topic = $this->client->getTopicRef($topicName);
        // as the messageBody will be automatically encoded
        // the MD5 is calculated for the encoded body
        $bodyMD5 = md5(base64_encode($messageBody));
        $request = new PublishMessageRequest($messageBody);
        try {
            $topic->publishMessage($request);
            if(config('aliyun.mns.debug')) logger("MessagePublished: {$topicName} => {$messageBody}");
        } catch (MnsException $e) {
            logger()->error("PublishMessage Failed: ", [
                'e' => $e,
            ]);
        }

    }
}