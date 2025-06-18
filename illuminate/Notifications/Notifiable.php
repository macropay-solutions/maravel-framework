<?php

namespace Illuminate\Notifications;

trait Notifiable
{
    use HasDatabaseNotifications;
    use RoutesNotifications;
}
