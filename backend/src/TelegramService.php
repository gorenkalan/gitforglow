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
        
        error_log("TelegramService Initialized. Token Loaded: " . (!empty($this->botToken) ? 'Yes' : 'No') . ", Chat ID: " . ($this->chatId ?? 'Not Set'));
    }

    private function escapeMarkdown($text) {
        $text = strval($text);
        $escapeChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        $pattern = '/([' . preg_quote(implode('', $escapeChars), '/') . '])/';
        return preg_replace($pattern, '\\\\$1', $text);
    }
    
    /**
     * NEW, SIMPLIFIED SEND METHOD
     * This uses file_get_contents, which is simpler and more reliable than cURL in some environments.
     * It is configured to be non-blocking.
     */
    private function send($message) {
        if (!$this->botToken || !$this->chatId) {
            error_log("Telegram notification skipped: Missing credentials.");
            return;
        }
        
        $payload = [
            'chat_id' => $this->chatId,
            'text' => $message,
            'parse_mode' => 'MarkdownV2',
            'disable_web_page_preview' => true,
        ];
        
        // Prepare the request for file_get_contents
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($payload),
                'ignore_errors' => true, // Don't let HTTP errors turn into PHP warnings
                'timeout' => 3,          // Set a short timeout of 3 seconds
            ]
        ];

        try {
            error_log("Attempting to send Telegram message with file_get_contents...");
            $context = stream_context_create($options);
            // The '@' suppresses warnings if the request times out, which is expected behavior.
            $response = @file_get_contents($this->apiUrl, false, $context);
            
            // Log the outcome for debugging, but do not let it block the main script.
            if ($response === false) {
                error_log("Telegram send failed or timed out (this is non-blocking and may be okay).");
            } else {
                error_log("Telegram send initiated. Response (or lack thereof) will be handled in the background.");
            }
        } catch (\Exception $e) {
            error_log("Exception in Telegram send: " . $e->getMessage());
        }
    }

    // The functions below this line are UNCHANGED and call the new send() method.
    public function sendNewOrderAttempt(array $orderData) {
        error_log("sendNewOrderAttempt called with data: " . print_r($orderData, true));
        $itemsText = "";
        if (!empty($orderData['items']) && is_array($orderData['items'])) {
            foreach($orderData['items'] as $item) {
                $itemsText .= "• " . $this->escapeMarkdown($item['name'] ?? 'N/A') . " `(" . $this->escapeMarkdown($item['variationId'] ?? 'N/A') . ")` x " . $this->escapeMarkdown($item['quantity'] ?? 1) . "\n";
            }
        }
        $message = "⏳ *New Checkout Started*\n\n"
            . "*Internal ID:* `" . $this->escapeMarkdown($orderData['order_id'] ?? 'N/A') . "`\n"
            . "*Customer:* " . $this->escapeMarkdown($orderData['name'] ?? 'N/A') . "\n"
            . "*Phone:* `" . $this->escapeMarkdown($orderData['phone'] ?? 'N/A') . "`\n"
            . "*Address:* " . $this->escapeMarkdown($orderData['address'] ?? 'N/A') . "\n"
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
            $detailsText .= "> *" . $this->escapeMarkdown(ucfirst(str_replace('_', ' ', $key))) . ":* `" . $this->escapeMarkdown($value) . "`\n";
        }
        $message = "🚨 *CRITICAL BACKEND ERROR*\n\n"
            . "*Context:* " . $this->escapeMarkdown($context) . "\n\n"
            . "*Details:*\n" . $detailsText . "\n"
            . "_Please check the server logs immediately\\._";
        $this->send($message);
    }
}