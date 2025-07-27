# Telegram Plugin

XBoard's Telegram Bot plugin, providing user account binding, traffic query, subscription link retrieval and other functions.

## Features

-   ✅ Ticket notification function (configurable switch)
-   ✅ Payment notification function (configurable switch)
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

View traffic usage of currently bound account.

### `/getlatesturl` - Get subscription link

Get the latest subscription link.

### `/unbind` - Unbind account

Unbind the association between current Telegram account and XBoard account.

## Configuration Options

### Basic Configuration

| Config Item    | Type    | Default Value                                                                                     | Description                |
| -------------- | ------- | ------------------------------------------------------------------------------------------------- | -------------------------- |
| `auto_reply`   | boolean | true                                                                                              | Whether to auto-reply unknown commands |
| `help_text`    | text    | 'Please use the following commands: \\n/bind - Bind account\\n/traffic - View traffic\\n/getlatesturl - Get latest link' | Reply text for unknown commands |

### `/start` Command Dynamic Configuration

| Config Item                  | Type | Description                     |
| ---------------------------- | ---- | ------------------------------- |
| `start_welcome_title`        | text | Welcome title                   |
| `start_bot_description`      | text | Bot feature introduction        |
| `start_bind_guide`           | text | Binding guide for unbound users |
| `start_unbind_guide`         | text | Command list for bound users    |
