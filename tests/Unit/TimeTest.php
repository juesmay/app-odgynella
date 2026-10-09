<?php

namespace Tests\Unit;

use App\Support\Time;
use PHPUnit\Framework\TestCase;

class TimeTest extends TestCase
{
    public function test_formats_hours_for_people(): void
    {
        $this->assertSame('8:00 a.m.', Time::human('08:00'));
        $this->assertSame('12:30 p.m.', Time::human('12:30'));
        $this->assertSame('2:15 p.m.', Time::human('14:15'));
    }

    public function test_money_round_trip(): void
    {
        $this->assertSame('$1.250.000', Time::money(1250000));
        $this->assertSame(1250000, Time::parseMoney('$ 1.250.000'));
        $this->assertSame(0, Time::parseMoney(''));
    }

    public function test_minutes_conversion(): void
    {
        $this->assertSame(570, Time::toMinutes('09:30'));
        $this->assertSame('09:30', Time::fromMinutes(570));
    }
}
