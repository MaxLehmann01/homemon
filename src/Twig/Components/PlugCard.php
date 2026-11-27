<?php

namespace App\Twig\Components;

use App\Entity\ShellyPlug;
use Psr\Clock\ClockInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent]
final class PlugCard
{
    use DefaultActionTrait;

    #[LiveProp]
    public ShellyPlug $plug;

    public function __construct(private readonly ChartBuilderInterface $charts, private readonly ClockInterface $clock)
    {
    }

    public function isStale(): bool
    {
        return null === $this->plug->getCheckedAt() || $this->plug->getCheckedAt() < $this->clock->now()->modify('-5 seconds');
    }

    #[ExposeInTemplate]
    public function getChart(): Chart
    {
        $now = $this->clock->now()->getTimestamp();
        $points = [];
        foreach ($this->plug->getPowerHistory() as $point) {
            if ($point['time'] >= $now - 300) {
                $points[$point['time']] = $point['watts'];
            }
        }
        $labels = $values = [];
        for ($time = $now - 299; $time <= $now; ++$time) {
            $labels[] = gmdate('H:i:s', $time);
            $values[] = $points[$time] ?? null;
        }

        $chart = $this->charts->createChart(Chart::TYPE_LINE);
        $chart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Power (W)',
                'data' => $values,
                'borderColor' => '#4f62d8',
                'backgroundColor' => 'rgba(79,98,216,0.09)',
                'borderWidth' => 2,
                'pointRadius' => 0,
                'pointHitRadius' => 10,
                'fill' => true,
                'spanGaps' => false,
                'tension' => 0.2,
            ]],
        ]);
        $chart->setOptions([
            'animation' => false,
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Watts (W)']],
                'x' => ['ticks' => ['maxTicksLimit' => 4, 'maxRotation' => 0], 'grid' => ['display' => false], 'title' => ['display' => true, 'text' => 'Time (UTC)']],
            ],
        ]);

        return $chart;
    }
}
