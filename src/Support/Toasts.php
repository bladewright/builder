<?php

namespace Bladewright\Support;

/**
 * Floats a message instead of planting it on the screen.
 *
 * **The words belong to the server.** Only how it appears is the browser's
 * job, so all that is thrown from here is what to say.
 */
trait Toasts
{
    protected function toast(string $message, string $tone = 'ok'): void
    {
        $this->dispatch('bw-toast', message: $message, tone: $tone);
    }

    protected function toastError(string $message): void
    {
        $this->toast($message, 'error');
    }
}
