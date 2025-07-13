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
        $text = strval($text);
        $escapeChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        $pattern = '/([' . preg_quote(implode('', $escapeChars), '/') . '])/';
        return preg_replace($pattern, '\\\\$1', $text);
    }
    
    private function send($message) {
        if (!$this->botToken || !$this->chatId) {
            error_log("Telegram credentials not set."); return;
        }
        $payload = ['chat_id' => $this->chatId, 'text' => $message, 'parse_mode' => 'MarkdownV2', 'disable_web_page_preview' => true];
        $options = ['http' => ['header'  => "Content-type: application/json\r\n", 'method'  => 'POST', 'content' => json_encode($payload), 'ignore_errors' => true, 'timeout' => 3]];
        try {
            $context = stream_context_create($options);
            @file_get_contents($this->apiUrl, false, $context);
        } catch (\Exception $e) {
            error_log("Exception in Telegram send: " . $e->getMessage());
        }
    }

    public function sendNewOrderAttempt(array $orderData) {
        $itemsText = "";
        if (!empty($orderData['items']) && is_array($orderData['items'])) {
            foreach($orderData['items'] as $item) {
                $itemsText .= "• " . $this->escapeMarkdown($item['name'] ?? 'N/A') . " `(" . $this->escapeMarkdown($item['variationId'] ?? 'N/A') . ")` x " . $this->escapeMarkdown($item['quantity'] ?? 1) . "\n";
            }
        }
        $message = "⏳ *New Checkout Started*\n\n"
            . "*Internal ID:* `" . $this->escapeMarkdown($orderData['order_id'] ?? 'N/A') . "`\n"
            . "*Customer:* " . $this->escapeMarkdown($orderData['customer_info']['name'] ?? 'N/A') . "\n"
            . "*Phone:* `" . $this->escapeMarkdown($orderData['customer_info']['phone'] ?? 'N/A') . "`\n"
            . "*Total:* *₹" . $this->escapeMarkdown(number_format($orderData['total'] ?? 0, 2)) . "*\n\n"
            . "*Items:*\n" . $itemsText . "\n"
            . "_User is proceeding to payment gateway\\._";
        $this->send($message);
    }
    
    public function sendOrderPaidConfirmation($orderId, $paymentId) {
        $message = "✅ *Payment Successful*\n\n"
            . "*Internal ID:* `" . $this->escapeMarkdown($orderId) . "`\n"
            . "*Payment ID:* `" . $this->escapeMarkdown($paymentId) . "`\n\n"
            . "_Order ready for syncing to Sheet\\._";
        $this->send($message);
    }
    
    public function sendCriticalError(string $context, array $details = []) {
        $detailsText = "";
        foreach ($details as $key => $value) {
            $detailsText .= "> *" . $this->escapeMarkdown(ucfirst(str_replace('_', ' ', $key))) . ":* `" . $this->escapeMarkdown(is_string($value) ? $value : json_encode($value)) . "`\n";
        }
        $message = "🚨 *CRITICAL BACKEND ERROR*\n\n"
            . "*Context:* " . $this->escapeMarkdown($context) . "\n\n"
            . "*Details:*\n" . $detailsText . "\n"
            . "_Please check the server logs immediately\\._";
        $this->send($message);
    }

    public function sendAbandonedCartNotification(array $abandonedOrderIds) {
        $count = count($abandonedOrderIds);
        $idList = "`" . implode("`\n`", $abandonedOrderIds) . "`";
        $message = "🧹 *Abandoned Carts Processed*\n\n"
            . "*Action:* Stock for *{$count}* abandoned order(s) has been replenished\\.\n\n"
            . "*Order IDs:*\n" . $idList . "\n\n"
            . "_You may want to follow up with these customers\\._";
        $this->send($message);
    }
}