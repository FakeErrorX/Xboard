<?php

namespace Plugin\Telegram;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Plugin\AbstractPlugin;
use App\Services\Plugin\HookManager;
use App\Services\TelegramService;
use App\Services\TicketService;
use App\Utils\Helper;
use Illuminate\Support\Facades\Log;

class Plugin extends AbstractPlugin
{
  protected array $commands = [];
  protected TelegramService $telegramService;

  protected array $commandConfigs = [
    '/start' => ['description' => 'Start using', 'handler' => 'handleStartCommand'],
    '/bind' => ['description' => 'Bind account', 'handler' => 'handleBindCommand'],
    '/traffic' => ['description' => 'Check traffic', 'handler' => 'handleTrafficCommand'],
    '/getlatesturl' => ['description' => 'Get subscription link', 'handler' => 'handleGetLatestUrlCommand'],
    '/unbind' => ['description' => 'Unbind account', 'handler' => 'handleUnbindCommand'],
  ];

  public function boot(): void
  {
    $this->telegramService = new TelegramService();
    $this->registerDefaultCommands();

    $this->filter('telegram.message.handle', [$this, 'handleMessage'], 10);
    $this->listen('telegram.message.unhandled', [$this, 'handleUnknownCommand'], 10);
    $this->listen('telegram.message.error', [$this, 'handleError'], 10);
    $this->filter('telegram.bot.commands', [$this, 'addBotCommands'], 10);
    $this->listen('ticket.create.after', [$this, 'sendTicketNotify'], 10);
    $this->listen('ticket.reply.user.after', [$this, 'sendTicketNotify'], 10);
    $this->listen('payment.notify.success', [$this, 'sendPaymentNotify'], 10);
  }

  public function sendPaymentNotify(Order $order): void
  {
    if (!$this->getConfig('enable_payment_notify', true)) {
      return;
    }

    $payment = $order->payment;
    if (!$payment) {
      Log::warning('Payment notification failed: Payment method associated with order does not exist', ['order_id' => $order->id]);
      return;
    }

    $message = sprintf(
      "💰Successfully received %s yuan\n" .
      "———————————————\n" .
      "Payment gateway: %s\n" .
      "Payment channel: %s\n" .
      "Site order: `%s`",
      $order->total_amount / 100,
      $payment->payment,
      $payment->name,
      $order->trade_no
    );
    $this->telegramService->sendMessageWithAdmin($message, true);
  }

  public function sendTicketNotify(Ticket $ticket): void
  {
    if (!$this->getConfig('enable_ticket_notify', true)) {
      return;
    }

    $message = $ticket->messages()->latest()->first();
    $user = User::find($ticket->user_id);
    if (!$user)
      return;
    $user->load('plan');
    $transfer_enable = Helper::transferToGB($user->transfer_enable);
    $remaining_traffic = Helper::transferToGB($user->transfer_enable - $user->u - $user->d);
    $u = Helper::transferToGB($user->u);
    $d = Helper::transferToGB($user->d);
    $expired_at = $user->expired_at ? date('Y-m-d H:i:s', $user->expired_at) : '';
    $money = $user->balance / 100;
    $affmoney = $user->commission_balance / 100;
    $plan = $user->plan;
    $ip = request()?->ip() ?? '';
    $region = $ip ? (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? (new \Ip2Region())->simple($ip) : 'NULL') : '';
    $TGmessage = "📮Ticket Reminder #{$ticket->id}\n———————————————\n";
    $TGmessage .= "Email: `{$user->email}`\n";
    $TGmessage .= "User location: \n`{$region}`\n";
    if ($plan) {
      $TGmessage .= "Plan and traffic: \n`{$plan->name} {$transfer_enable}/{$remaining_traffic}`\n";
      $TGmessage .= "Upload/Download: \n`{$u}/{$d}`\n";
      $TGmessage .= "Expiry time: \n`{$expired_at}`\n";
    } else {
      $TGmessage .= "Plan and traffic: \n`No plan subscribed`\n";
    }
    $TGmessage .= "Balance/Commission balance: \n`{$money}/{$affmoney}`\n";
    $TGmessage .= "Subject:\n`{$ticket->subject}`\nContent:\n`{$message->message}`\n";
    $this->telegramService->sendMessageWithAdmin($TGmessage, true);
  }

  protected function registerDefaultCommands(): void
  {
    foreach ($this->commandConfigs as $command => $config) {
      $this->registerTelegramCommand($command, [$this, $config['handler']]);
    }

    $this->registerReplyHandler('/(Ticket Reminder #?|Ticket ID: ?)(\\d+)/', [$this, 'handleTicketReply']);
  }

  public function registerTelegramCommand(string $command, callable $handler): void
  {
    $this->commands['commands'][$command] = $handler;
  }

  public function registerReplyHandler(string $regex, callable $handler): void
  {
    $this->commands['replies'][$regex] = $handler;
  }

  /**
   * Send message to user
   */
  protected function sendMessage(object $msg, string $message): void
  {
    $this->telegramService->sendMessage($msg->chat_id, $message, 'markdown');
  }

  /**
   * Check if it's a private chat
   */
  protected function checkPrivateChat(object $msg): bool
  {
    if (!$msg->is_private) {
      $this->sendMessage($msg, 'Please use this command in private chat');
      return false;
    }
    return true;
  }

  /**
   * Get bound user
   */
  protected function getBoundUser(object $msg): ?User
  {
    $user = User::where('telegram_id', $msg->chat_id)->first();
    if (!$user) {
      $this->sendMessage($msg, 'Please bind your account first');
      return null;
    }
    return $user;
  }

  public function handleStartCommand(object $msg): void
  {
    $welcomeTitle = $this->getConfig('start_welcome_title', '🎉 Welcome to XBoard Telegram Bot!');
    $botDescription = $this->getConfig('start_bot_description', '🤖 I am your dedicated assistant, I can help you:\\n• Bind your XBoard account\\n• Check traffic usage\\n• Get the latest subscription link\\n• Manage account binding status');
    $footer = $this->getConfig('start_footer', '💡 Tip: All commands need to be used in private chat');

    $welcomeText = $welcomeTitle . "\n\n" . $botDescription . "\n\n";

    $user = User::where('telegram_id', $msg->chat_id)->first();
    if ($user) {
      $welcomeText .= "✅ Your account is bound: {$user->email}\n\n";
      $welcomeText .= $this->getConfig('start_unbind_guide', '📋 Available commands:\\n/traffic - Check traffic usage\\n/getlatesturl - Get subscription link\\n/unbind - Unbind account');
    } else {
      $welcomeText .= $this->getConfig('start_bind_guide', '🔗 Please first bind your XBoard account:\\n1. Login to your XBoard account\\n2. Copy your subscription link\\n3. Send /bind + subscription link') . "\n\n";
      $welcomeText .= $this->getConfig('start_bind_commands', '📋 Available commands:\\n/bind [subscription link] - Bind account');
    }

    $welcomeText .= "\n\n" . $footer;
    $welcomeText = str_replace('\\n', "\n", $welcomeText);

    $this->sendMessage($msg, $welcomeText);
  }

  public function handleMessage(bool $handled, array $data): bool
  {
    list($msg) = $data;
    if ($handled)
      return $handled;

    try {
      return match ($msg->message_type) {
        'message' => $this->handleCommandMessage($msg),
        'reply_message' => $this->handleReplyMessage($msg),
        default => false
      };
    } catch (\Exception $e) {
      Log::error('Telegram command processing unexpected error', [
        'command' => $msg->command ?? 'unknown',
        'chat_id' => $msg->chat_id ?? 'unknown',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
      ]);

      if (isset($msg->chat_id)) {
        $this->telegramService->sendMessage($msg->chat_id, 'System is busy, please try again later');
      }

      return true;
    }
  }

  protected function handleCommandMessage(object $msg): bool
  {
    if (!isset($this->commands['commands'][$msg->command])) {
      return false;
    }

    call_user_func($this->commands['commands'][$msg->command], $msg);
    return true;
  }

  protected function handleReplyMessage(object $msg): bool
  {
    if (!isset($this->commands['replies'])) {
      return false;
    }

    foreach ($this->commands['replies'] as $regex => $handler) {
      if (preg_match($regex, $msg->reply_text, $matches)) {
        call_user_func($handler, $msg, $matches);
        return true;
      }
    }

    return false;
  }

  public function handleUnknownCommand(array $data): void
  {
    list($msg) = $data;
    if (!$msg->is_private || $msg->message_type !== 'message')
      return;

    $helpText = $this->getConfig('help_text', 'Unknown command, please check help');
    $this->telegramService->sendMessage($msg->chat_id, $helpText);
  }

  public function handleError(array $data): void
  {
    list($msg, $e) = $data;
    Log::error('Telegram message processing error', [
      'chat_id' => $msg->chat_id ?? 'unknown',
      'command' => $msg->command ?? 'unknown',
      'message_type' => $msg->message_type ?? 'unknown',
      'error' => $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine()
    ]);
  }

  public function handleBindCommand(object $msg): void
  {
    if (!$this->checkPrivateChat($msg)) {
      return;
    }

    $subscribeUrl = $msg->args[0] ?? null;
    if (!$subscribeUrl) {
      $this->sendMessage($msg, 'Invalid parameters, please send with subscription URL');
      return;
    }

    $token = $this->extractTokenFromUrl($subscribeUrl);
    if (!$token) {
      $this->sendMessage($msg, 'Invalid subscription URL');
      return;
    }

    $user = User::where('token', $token)->first();
    if (!$user) {
      $this->sendMessage($msg, 'User does not exist');
      return;
    }

    if ($user->telegram_id) {
      $this->sendMessage($msg, 'This account is already bound to a Telegram account');
      return;
    }

    $user->telegram_id = $msg->chat_id;
    if (!$user->save()) {
      $this->sendMessage($msg, 'Setting failed');
      return;
    }

    HookManager::call('user.telegram.bind.after', [$user]);
    $this->sendMessage($msg, 'Successfully bound');
  }

  protected function extractTokenFromUrl(string $url): ?string
  {
    $parsedUrl = parse_url($url);

    if (isset($parsedUrl['query'])) {
      parse_str($parsedUrl['query'], $query);
      if (isset($query['token'])) {
        return $query['token'];
      }
    }

    if (isset($parsedUrl['path'])) {
      $pathParts = explode('/', trim($parsedUrl['path'], '/'));
      $lastPart = end($pathParts);
      return $lastPart ?: null;
    }

    return null;
  }

  public function handleTrafficCommand(object $msg): void
  {
    if (!$this->checkPrivateChat($msg)) {
      return;
    }

    $user = $this->getBoundUser($msg);
    if (!$user) {
      return;
    }

    $transferUsed = $user->u + $user->d;
    $transferTotal = $user->transfer_enable;
    $transferRemaining = $transferTotal - $transferUsed;
    $usagePercentage = $transferTotal > 0 ? ($transferUsed / $transferTotal) * 100 : 0;

    $text = sprintf(
      "📊 Traffic Usage Status\n\nUsed Traffic: %s\nTotal Traffic: %s\nRemaining Traffic: %s\nUsage Rate: %.2f%%",
      Helper::transferToGB($transferUsed),
      Helper::transferToGB($transferTotal),
      Helper::transferToGB($transferRemaining),
      $usagePercentage
    );

    $this->sendMessage($msg, $text);
  }

  public function handleGetLatestUrlCommand(object $msg): void
  {
    if (!$this->checkPrivateChat($msg)) {
      return;
    }

    $user = $this->getBoundUser($msg);
    if (!$user) {
      return;
    }

    $subscribeUrl = Helper::getSubscribeUrl($user->token);
    $text = sprintf("🔗 Your subscription link:\n\n%s", $subscribeUrl);

    $this->sendMessage($msg, $text);
  }

  public function handleUnbindCommand(object $msg): void
  {
    if (!$this->checkPrivateChat($msg)) {
      return;
    }

    $user = $this->getBoundUser($msg);
    if (!$user) {
      return;
    }

    $user->telegram_id = null;
    if (!$user->save()) {
      $this->sendMessage($msg, 'Unbind failed');
      return;
    }

    $this->sendMessage($msg, 'Successfully unbound');
  }

  public function handleTicketReply(object $msg, array $matches): void
  {
    $user = $this->getBoundUser($msg);
    if (!$user) {
      return;
    }

    if (!isset($matches[2]) || !is_numeric($matches[2])) {
      Log::warning('Telegram ticket reply regex failed to match ticket ID', ['matches' => $matches, 'msg' => $msg]);
      $this->sendMessage($msg, 'Unable to identify ticket ID, please reply directly to the ticket notification message.');
      return;
    }

    $ticketId = (int) $matches[2];
    $ticket = Ticket::where('id', $ticketId)->first();
    if (!$ticket) {
      $this->sendMessage($msg, 'Ticket does not exist');
      return;
    }

    $ticketService = new TicketService();
    $ticketService->replyByAdmin(
      $ticketId,
      $msg->text,
      $user->id
    );

    $this->sendMessage($msg, "Ticket #{$ticketId} reply successful");
  }

  /**
   * Add Bot commands to command list
   */
  public function addBotCommands(array $commands): array
  {
    foreach ($this->commandConfigs as $command => $config) {
      $commands[] = [
        'command' => $command,
        'description' => $config['description']
      ];
    }

    return $commands;
  }

}