<?php
namespace app\index\controller;
use EthereumRPC\EthereumRPC;
use ERC20\ERC20;
use think\Db;
use HttpClient\HttpClient;

class Eth
{
    public function index()
    {
        exit;
    }
  
    /**
    *创建账号
    */
    public function newAccount(){
        $uid = input('?post.uid');
        $cointype = input('?post.cointype');
        $phone = input('?post.phone');
        $reg_time = input('?post.reg_time');
        if($uid==""){
            $data['code']= 0;
            $data['msg']= "uid不能为空！";
            echo json_encode($data);exit;
        }
        //生成ERC20代币地址密码
        $password = md5($phone.$reg_time);
        $wallet =  Db::table("data_wallet")->where(['member_id' => $uid, 'cointype' => $cointype])->find();
        if($wallet){
            $data['code']= 1;
            $data['account'] = $wallet['account'];
            $data['cointype'] = 1;
        }else{
            //无账户新增账户
            $geth = new EthereumRPC('127.0.0.1', 4546);
            $acc = $geth->personal()->newAccount($password);
            if(strlen($acc) != 42){
                $data['code']= 0;
            }else{
                $data['code']= 1;
            }
            $data['account'] = $acc;
            $data['cointype'] = 1;
            
            $account['member_id'] = $uid;
            $account['create_time'] = time();
            $account['account'] = $acc;
            $account['cointype'] = $cointype;
            $account['password'] = $password;
            $account['phone'] = $phone;
            $account['reg_time'] = $reg_time;
            unset($account['id']);
            $wallet = Db::table("data_wallet")->insert($account);
        }

        echo json_encode($data);
    }

public function object2array($object) {
    if (is_object($object)) {
        foreach ($object as $key => $value) {
            $array[$key] = $value;
        }
    }
    else {
        $array = $object;
    }
    return $array;
}

/**
    *转入提醒
    */
    public function sendInputRemind(){
        $geth = new EthereumRPC('47.75.211.169', 39088);
        //获取geth中当前最高区块
        $eth_syncing = $geth->jsonRPC('eth_syncing')->get('result');
        //将16进制转为10进制
        $current = $currentBlock = base_convert(substr($eth_syncing['currentBlock'], 2), 16, 10);  //6397310

        //获取数据库中的最新区块
        $block = Db::table("data_current_block")->order('id desc')->find();

        //当前区块是否高于我们区块
        if($currentBlock > $block['currentblock']){
            //查找是否有我们的地址
            $times = $currentBlock - $block['currentblock'];
            for($i = 1; $i <= $times; $i++ ){
                $currentBlock = $block['currentblock']+$i;//未查询转入的区块

                //$blockInfo= $geth->eth()->getBlock($currentBlock);
                $currentBlock = "0x".base_convert(($currentBlock),10,16);
                //根据区块ID获取当前区块搜包含的所有交易记录
                $param = array($currentBlock,true);
                $blockInfo = $geth->jsonRPC('eth_getBlockByNumber','', $param )->get('result');
                $transactions = $blockInfo['transactions'];
                //循环筛选数据库区块到当前区块之间是否给我的 地址转过代币
                for($i=0;$i<count($transactions);$i++){
                    if($transactions[$i]['to'] =='erc20 token contract'){//是否交易的是PEC代币
                        //从 transactions[$i]['input'] 中获取接收代币的地址和数量
                        $input = explode('000000000000000000000000000000000000000000000',$transactions[$i]['input']);
                        $acc = explode('0xa9059cbb000000000000000000000000',$input[0]);
                        $account = "0x".$acc[1];
                        //是否是我们钱包
                        $res = Db::table("data_wallet")->where(array("account"=>$account))->find();
                        if($res){
                            $param['code'] = 1;
                            $param['cointype'] = 1;
                            $param['account'] = $account;
                            $param['txhash'] = $transactions[$i]['hash'];
                            $param['num'] = base_convert($input[1],16,10)/1000000000000000000;//16进制转10进制 Gwei转为整数
                            //给PEC APP 通知同步加币
                            $url ="http://yoursite.org/Api/Plan/handle_out";//加币接口
                            $res = $HttpClient->Post($url)->payload($param)->send();
                            if($res['returnCode'] =='SUCCESS'){
                                //加币成功 添加交易记录
                                $receive_trans['blockNumber'] = base_convert(substr($transactions[$i]['blockNumber'], 2),16,10);
                                $receive_trans['txhash'] = $transactions[$i]['hash'];
                                $receive_trans['sendFrom'] = $transactions[$i]['from'];
                                $receive_trans['sendTo'] = $account;
                                $receive_trans['cointype'] = 1;
                                $receive_trans['num'] = $param['num'];
                                $receive_trans['create_time'] =time();
                                $receive_trans['status'] = 1;
                                $receive_trans['is_add_coin'] = 1;
                                $log = Db::table("data_receive_trans")->insert($receive_trans);
                            }
                            
                        }
                    }
                }
            }
                //记录已查询过的区块数量
                $current_blockD['currentblock'] = $current;
                $current_blockD['currentBlockEth'] = "0x".base_convert($current,10,16);
                $current_blockD['update_time'] = time();
                $current_block = Db::table("data_current_block")->where('id', $block['id'])->update($current_blockD);
        }
    }
    
    
    /**
     *转出确认
     */
    public function outRemind(){ 
        $sendTrans = Db::table("data_send_trans")->where(array("status"=>0))->select();
        //查询PEC APP未处理的交易
        for($i=0;$i<count($sendTrans);$i++){
            $geth = new EthereumRPC('192.168.1.1', 8082);
            $param = array($sendTrans[$i]['txHash']);
            //获取交易记录
            $result = $geth->jsonRPC('eth_getTransactionReceipt','', $param )->get('result');
            //如果 logs有数据，就说明交易成功
            if($result['logs'][0]['address']=='erc20 token contract'){
                //交易成功 给PEC客户断通知减币
                $param['code'] = 1;
                $param['cointype'] = 1;
                $param['txhash'] = $sendTrans[$i]['txHash'];
                $url ="http://yoursite.org/Api/Plan/handle_out";
                $HttpClient = new HttpClient();
                $res = $HttpClient->Post($url)->payload($param)->send();
                //修改数据库状态
                if($res['returnCode'] =='SUCCESS'){
                    $hashData['status'] = 1;
                    $hashData['is_send'] = 1;
                    Db::table("data_send_trans")->where('id',$sendTrans[$i]['id'])->update($hashData);
                }
            }
        }
        
    }
    //推送的Curl方法
    public function post($uri="",$param="",$header="") {
        if (empty($param)) { return false; }
        $postUrl = $uri;
        $curlPost = $param;
  
 
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$postUrl);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $data = curl_exec($ch);
        curl_close($ch);
        return json_decode($data,true);
    }

    /**
    *转出Coin
    */
    public function sendToken(){
    
        $num = input('?post.num');
        $uid  =input('?post.uid');//发送者UID
        $account = input('?post.account');//收币地址
        $exchange_id =input('?post.exchange_id');//交易所ID
        $cointype = input('?post.cointype');
        $wallet = Db::table("data_wallet")->where(['member_id' => $uid, 'cointype' => $cointype])->find();
        if(!$wallet){
            $res['code'] = 0;
            $res['txHash'] = '用户钱包地址不存在';
        }else{
            //to do .... send
            $geth = new EthereumRPC('127.0.0.1', 4546);
            $erc20 = new ERC20($geth);
            $contract = ''; //合约地址
            $payer = "";//付币者地址
            //$payee = "";//收币地址
            $payee = $account;//收币地址
            $amount = $num;
            $token = $erc20->token($contract);
            $data = $token->encodedTransferData($payee, $amount);
            $transaction = $geth->personal()->transaction($payer, $contract) // from $payer to $contract address
              ->amount("0") // Amount should be ZERO
              ->data($data); // Our encoded ERC20 token transfer data from previous step
            // Send transaction with ETH account passphrase
            $txId = $transaction->send("xiaoxiong"); // Replace "secret" with actual passphrase of SENDER's ethereum
            //dump($txId);
            //发送代币成功后添加记录
            $sendTrans['sendFromUid'] = $uid;
            $sendTrans['sendFrom'] = $wallet['account'];
            $sendTrans['sendTo'] = $account;
            $sendTrans['exchange_id'] = $exchange_id;
            $sendTrans['cointype'] = $cointype;
            $sendTrans['num'] = $num;
            $sendTrans['txHash'] = $txId;
            $sendTrans['create_time'] = time();
            unset($sendTrans['id']);
            Db::table("data_send_trans")->insert($sendTrans);
            
            $res['code'] = 1;
            $res['txHash'] = $txId;
            
        }
        echo json_encode($res);
    }


}
