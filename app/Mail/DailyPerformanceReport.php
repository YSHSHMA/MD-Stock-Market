<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyPerformanceReport extends Mailable
{
  use Queueable, SerializesModels;

  public function __construct(
    public readonly string $reportText,
    public readonly string $userName,
    public readonly ?string $csvUrl = null
  ) {}

  public function envelope(): Envelope
  {
    return new Envelope(
      subject: 'Daily Portfolio Performance Report - ' . now()->format('M d, Y'),
    );
  }

  public function content(): Content
  {
    return new Content(
      view: 'emails.daily-report',
    );
  }

  /**
   * @return array<int, Attachment>
   */
  public function attachments(): array
  {
    return [];
  }
}
