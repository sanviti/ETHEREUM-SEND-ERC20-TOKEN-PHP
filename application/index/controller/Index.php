<?php
namespace app\index\controller;
use EthereumRPC\EthereumRPC;
use ERC20\ERC20;
class Index
{
    public function index()
    {
        exit;
    }
  
  	public function test(){
     	$geth = new EthereumRPC('127.0.0.1', 8080);
     	$response = $geth->jsonRPC('eth_accounts');
     	dump($response);

    }

    public function test(){
    	$geth = new EthereumRPC('127.0.0.1', 8080);
        $erc20 = new ERC20($geth);
        $contract = ''; //合约地址
        $payer = "0xc29e71f7d3fb40cde79f9ba9d670021a907ec16e";//收币地址
        $payee = "0x24Ad2EBBE8423Fea8335E269DF8Db6B2D4647313";//付币地址
        $amount = "1.22";
        $token = $erc20->token($contract);
        $data = $token->encodedTransferData($payee, $amount);
        $transaction = $geth->personal()->transaction($payer, $contract) // from $payer to $contract address
		  ->amount("0") // Amount should be ZERO
		  ->data($data); // Our encoded ERC20 token transfer data from previous step
		// Send transaction with ETH account passphrase
		$txId = $transaction->send("xiaoxiongs"); // Replace "secret" with actual passphrase of SENDER's ethereum
		dump($txId);
    }


}
