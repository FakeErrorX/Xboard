# Telegram Plugin

Telegram Bot plugin for XBoard, providing user account binding, traffic query, subscription link retrieval and other functions.

## Features

-   ✅ Ticket notification function (configurable)
-   ✅ Payment notification function (configurable)
-   ✅ User account binding/unbinding
-   ✅ Traffic usage query
-   ✅ Subscription link retrieval
-   ✅ Ticket reply support

## Available Commands

### `/start` - Start using

Welcome new users and display help information, supports dynamic configuration.

### `/bind` - Bind account

Bind user's XBoard account to Telegram.

```
/bind [subscription link]
```

### `/traffic` - View traffic

View current bound account's traffic usage.

### `/getlatesturl` - Get subscription link

Get the latest subscription link.

### `/unbind` - Unbind account

Unbind the association between current Telegram account and XBoard account.

## Configuration Options

### Basic Configuration

| Config Item  | Type    | Default Value                                                                                            | Description                             |
| ------------ | ------- | -------------------------------------------------------------------------------------------------------- | --------------------------------------- |
| `auto_reply` | boolean | true                                                                                                     | Whether to auto-reply to unknown commands |
| `help_text`  | text    | 'Please use the following commands:\\n/bind - Bind account\\n/traffic - View traffic\\n/getlatesturl - Get latest link' | Reply text for unknown commands         |

### `/start` Command Dynamic Configuration

| Config Item             | Type | Description              |
| ----------------------- | ---- | ------------------------ |
| `start_welcome_title`   | text | Welcome title                 |
| `start_bot_description` | text | Bot function introduction           |
| `start_bind_guide`      | text | Binding guide for unbound users     |
| `start_unbind_guide`    | text | Command list for bound users |
| `start_bind_commands`   | text | Command list for unbound users |
| `start_footer`          | text | Footer hint information             |

### Ticket Notification Configuration

| Config Item            | Type    | Default | Description                 |
| ---------------------- | ------- | ------- | --------------------------- |
| `enable_ticket_notify` | boolean | true    | Whether to enable ticket notification |

### Payment Notification Configuration

| Config Item             | Type    | Default | Description                 |
| ----------------------- | ------- | ------- | --------------------------- |
| `enable_payment_notify` | boolean | true    | Whether to enable payment notification |

## Usage Process

### New User Process

1. User first uses Bot, send `/start`
2. Bind account according to prompts: `/bind [subscription link]`
3. After successful binding, you can use other functions

### Daily Usage Process

1. View traffic: `/traffic`
2. Get subscription link: `/getlatesturl`
3. Manage binding: `/unbind`
