<?php
namespace App;

class TelegramService {
    private $botToken;
    private $chatId;
    private $apiUrl;

    public function __construct() {
        $this->botToken = Config::get('TELEGRAM_BOT_TOKEN');
        $this->chatId = Config::get('TELEGRAM_CHAT_ID');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
    }

    private function escapeMarkdown($text) {
        $escapeChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        return str_replace($escapeChars, array_map(function($char) { return "\\$char"; }, $escapeChars), $text);
    }
    
    private function send($payload) {
        if (!$this->botToken || !$this->chatId) return;
        try {
            $ch = curl_init($this->apiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            error_log("Failed to send Telegram notification: " . $e->getMessage());
        }
    }

    public function sendNewOrderAttempt(array $orderData) {
        $itemsText = "";
        foreach($orderData['items'] as $item) {
            $itemsText .= "• " . $this->escapeMarkdown($item['name']) . " (" . $this->escapeMarkdown($item['variationId']) . ") x " . $this->escapeMarkdown($item['quantity']) . "\n";
        }

        $message = "⏳ *New Checkout Started*\n\n"
            . "*Order ID:* `" . $this->escapeMarkdown($orderData['order_id']) . "`\n"
            . "*Customer:* " . $this->escapeMarkdown($orderData['name']) . "\n"
            . "*Phone:* " . $this->escapeMarkdown($orderData['phone']) . "\n"
            . "*Total:* ₹" . $this->escapeMarkdown(number_format($orderData['total'], 2)) . "\n\n"
            . "*Items:*\n" . $itemsText;

        $this->send(['chat_id' => $this->chatId, 'text' => $message, 'parse_mode' => 'MarkdownV2']);
    }
    
    public function sendOrderPaidConfirmation($orderId, $paymentId) {
        $message = "✅ *Payment Successful*\n\n"
            . "*Order ID:* `" . $this->escapeMarkdown($orderId) . "`\n"
            . "*Payment ID:* `" . $this->escapeMarkdown($paymentId) . "`\n\n"
            . "The order sheet has been updated to 'Paid' status.";

        $this->send(['chat_id' => $this->chatId, 'text' => $message, 'parse_mode' => 'MarkdownV2']);
    }
}