<?php

declare(strict_types=1);

use CourseHub\WebPlatform\Shared\Http\Response;
use CourseHub\WebPlatform\Shared\Ui\PortalPage;

final class InstructorSalesPage
{
    public static function render(array $sales, string $error = ''): Response
    {
        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $gross = $commission = $net = 0.0;
        $rows = '';
        foreach ($sales as $sale) {
            $gross += (float) ($sale['gross_amount'] ?? 0);
            $commission += (float) ($sale['commission_amount'] ?? 0);
            $net += (float) ($sale['instructor_amount'] ?? 0);
            $rows .= '<tr><td>#' . (int) ($sale['id'] ?? 0) . '</td><td>' . $e($sale['course_title'] ?? '') . '</td><td>' . $e($sale['student_name'] ?? '') . '</td><td>#' . (int) ($sale['order_id'] ?? 0) . '</td><td>NPR ' . number_format((float) ($sale['gross_amount'] ?? 0), 2) . '</td><td>− NPR ' . number_format((float) ($sale['commission_amount'] ?? 0), 2) . '</td><td>NPR ' . number_format((float) ($sale['instructor_amount'] ?? 0), 2) . '</td><td><span class="status-badge ' . $e($sale['earning_status'] ?? 'available') . '">' . $e($sale['earning_status'] ?? 'available') . '</span></td><td>' . $e($sale['created_at'] ?? '') . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr class="empty-row"><td colspan="9"><div><span>⌁</span><strong>No verified sales yet</strong><small>Approved student payments create course earnings automatically.</small></div></td></tr>';
        }
        $alert = $error !== '' ? '<div class="form-alert error">' . $e($error) . '</div>' : '';
        $content = $alert . '<section class="metric-grid"><article class="metric-card blue"><div class="metric-top"><span>Verified sales</span><i></i></div><strong>' . count($sales) . '</strong><small>Payment-linked order items</small></article><article class="metric-card violet"><div class="metric-top"><span>Gross revenue</span><i></i></div><strong>NPR ' . number_format($gross, 2) . '</strong><small>Before platform commission</small></article><article class="metric-card teal"><div class="metric-top"><span>Instructor earnings</span><i></i></div><strong>NPR ' . number_format($net, 2) . '</strong><small>Net earning ledger</small></article><article class="metric-card orange"><div class="metric-top"><span>Platform commission</span><i></i></div><strong>NPR ' . number_format($commission, 2) . '</strong><small>Recorded per order item</small></article></section>'
            . '<section class="data-card"><div class="data-card-head"><div><span>SALES LEDGER</span><h3>Verified course earnings</h3></div><a class="portal-button" href="/instructor/withdrawals">Withdraw earnings</a></div><div class="table-wrap"><table><thead><tr><th>Earning</th><th>Course</th><th>Student</th><th>Order</th><th>Gross</th><th>Commission</th><th>Net</th><th>Status</th><th>Date</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';
        return PortalPage::render('instructor', 'Sales', $content);
    }
}
