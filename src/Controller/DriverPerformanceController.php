<?php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use InvalidArgumentException;

final class DriverPerformanceController extends AbstractController
{
    #[Route('/driver-performance', name: 'driver_performance_hub', methods: ['GET', 'POST'])]
    public function index(EntityManagerInterface $em, Request $request, Connection $db, CsrfTokenManagerInterface $csrfManager): Response
    {
        $level = SectionAccess::performanceLevel($this->getUser());
        if ($level === 'none') throw $this->createAccessDeniedException('Driver Performance access is not assigned.');
        $full = $level === 'full';
        $selectedWeek = (string) $request->query->get('week', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedWeek) || !($date = \DateTimeImmutable::createFromFormat('!Y-m-d', $selectedWeek)) || $date->format('Y-m-d') !== $selectedWeek) {
            $date = new \DateTimeImmutable('now', new \DateTimeZone('Europe/London'));
        }
        $weekStart = $date->modify('monday this week')->format('Y-m-d');
        $tab = in_array($request->query->get('tab'), ['cc', 'pod', 'dsp', 'dwc', 'rules', 'report'], true) ? $request->query->get('tab') : 'weekly';
        $scoreCategories = ['fico' => ['FICO/EMENTOR (Safety · Ementor Score)', 20], 'dwc' => ['DWC (Deliveries · Delivery Workflow Compliance)', 10], 'psb' => ['PSB (Collections · Collection Fails /100 stops)', 5], 'dsc_dpmo' => ['DSC DPMO (Concessions)', 20], 'pod' => ['POD (Picture on Delivery)', 10], 'cc' => ['CC (Contact Compliance)', 15], 'ce' => ['CE (Escalations)', 15], 'cdf_dpmo' => ['DEX / CDF DPMO (Negative Feedback)', 5]];
        $ficoColors = ['red_below' => 800, 'amber_below' => 810, 'red_color' => '#ffc7ce', 'amber_color' => '#ffeb9c', 'green_color' => '#c6efce'];
        $dwcColors = ['red_below' => 90, 'green_above' => 95, 'red_color' => '#ffc7ce', 'amber_color' => '#ffeb9c', 'green_color' => '#c6efce'];
        $dpmoBands = [[3000,0],[2000,1],[1500,2.5],[1400,4],[1300,5],[1200,7],[1100,8],[1000,10],[900,13],[700,16],[600,18],[575,19],[550,20]];
        $dpmoColors = ["green_at_or_below" => 550, "red_above" => 1000, "green_color" => "#c6efce", "amber_color" => "#ffeb9c", "red_color" => "#ffc7ce"];
        $podBands = [[99.9,10],[99.5,9],[99,8.5],[98.5,5],[98,4],[97.5,3],[97,1],[96.5,0.75],[95,0.5],[94,0.25],[90,0]];
        $cdfBands = [[8000,0],[7000,1],[6000,1.5],[5000,2.5],[4500,4]];
        $cdfBelow = 4420.0;
        $cdfBelowPoints = 5.0;
        $cdfColors = ['green_below'=>1,'red_above'=>4,'green_color'=>'#c6efce','amber_color'=>'#ffeb9c','red_color'=>'#ffc7ce'];
        $ceBands = [[0,15]];
        $ceAbovePoints = 0.0;
        $ceColors = ['zero_color' => '#c6efce', 'positive_color' => '#ffc7ce', 'missing_color' => '#c6efce'];
        $ccBands = [[99.99,15],[99.8,12],[99.7,10],[99.6,9.5],[99.5,9],[99.3,8],[99.2,7],[98.8,5],[98.6,4.5],[98.5,4],[98.3,3],[98,2],[97,1],[96,0]];
        $ccColors = ['red_below' => 98, 'green_above' => 98.99, 'red_color' => '#ffc7ce', 'amber_color' => '#ffeb9c', 'green_color' => '#c6efce'];
        $podColors = ["red_below" => 97, "green_above" => 99.1, "green_color" => "#c6efce", "amber_color" => "#ffeb9c", "red_color" => "#ffc7ce"];
        $psbThreshold = 3.0;
        $psbPoints = 5.0;
        $psbBands = [[3.0,5.0]];
        $psbColors = ['green_color' => '#c6efce', 'red_color' => '#ffc7ce'];
        $dwcBands = [[99,10],[97.5,9],[95,8.5],[92.5,5],[90,4],[87.5,3],[85,1],[82.5,0.75],[80,0.5],[78.5,0.25],[75,0]];
        $ficoBands = [[845,20],[840,19],[835,18],[830,17],[820,16],[810,14],[800,8],[790,3],[780,2],[770,1],[760,0]];
        $focusDefaults = ['NONE'=>'Excellent Week, Well done','DCR'=>'DCR - Use customers safe place options, contact customers and ensure you reattempt all problem deliveries.','DPMO'=>'DPMO / Concessions - Please discuss your concessions with OSM. This will help you understand how to improve.','POD'=>'POD - Please follow picture on delivery process. Do not bypass pictures on attended deliveries.','CE'=>'Customer Escalation - Reach out to the OSM team for reasons behind your escalation and ways to improve.','DWC'=>'Delivery Workflow and In App Compliance - Read notes and use customers safe place preferences.','CC'=>'CC - When prompted to contact, ring for at least 15 seconds before hanging up, or text.','NOA'=>'NOA - Use the notify on arrival feature to improve contact compliance and customer feedback.','DEX'=>'Negative Customer Feedback - Follow delivery instructions, be mindful of customer property and follow correct delivery procedures.','FICO'=>'FICO - Driving safety score on Ementor needs to be 810+ to be classed as a safe driver.','NET'=>'Netradyne - Drive safely. Traffic lights, distractions, following distance and stop signs affect the team scorecard.','PBS'=>'Pickup Success - Collect the yellow returns label for customer returns, scan and complete collections at the pin.'];
        $focusMessages = $focusDefaults;
        $focusHeadings = array_combine(array_keys($focusDefaults), array_keys($focusDefaults));
        $scorecardExplanation = <<<'SCORECARD_DEFAULT'
<div class="sc-explainer-grid"><div><b>Status</b> - Quality ranking: Poor / Fair / Great / Fantastic / Fantastic Plus. Reference note: GBP 5/day increase for Fantastic or above.</div><div><b>Total Score</b> - Combined percentage of scored areas out of 100.</div><div><b>Ementor score - 20%</b> - Driving Safety standard score. Target 810+; review daily with OSM.</div><div><b>Netradyne Violations</b> - In-cab cameras on fleet vans. 2% deducted per violation in other categories. Traffic Light retains its existing 2% per affected category pending clarification.</div><div><b>Delivery Completion Rate</b> - Percentage of delivered parcels. Target 99.2%+.</div><div><b>Delivery Workflow Compliance - 10%</b> - Percentage of delivered shipments where the delivery location used matches the location recommended by the Delivery App.</div><div><b>Collections - 5%</b> - Collections completed correctly at the pin or delivered in the app and scanned back to station correctly.</div><div><b>DSC - Delivery Success Conditions / DPMO - 20%</b> - Defects per million opportunities used to score concessions. Target 550 or below.</div><div><b>QTY</b> - Concessions, refunds or replacements due to weather damage or theft. Target 0.</div><div><b>POD - 10%</b> - Picture on Delivery compliance for safe places and attended deliveries. Target 99% or above.</div><div><b>CC - 15%</b> - Contact Compliance for prompted customer contacts. Target 99% or above.</div><div><b>CE - 15%</b> - Customer Escalations: behavioural or procedural escalations from customers. Target 0.</div><div><b>Customer Feedback - 5%</b> - Negative customer feedback in DPMO format. Green threshold 5,377 or below.</div></div><p>Traffic Light rule pending confirmation. Configurable scoring bands may differ from reference targets.</p></section>
SCORECARD_DEFAULT;
        $reportTitles = PerformanceReportTitle::DEFAULTS;
        $decoded = [];
        $netradyneMultipliers = ['d'=>2.0, 'fd'=>2.0, 'ss'=>2.0, 'speed'=>2.0, 'tl'=>10.0];
        $scoreRules = [];
        foreach ($scoreCategories as $key => [$label, $points]) $scoreRules[$key] = ['label' => $label, 'maximum' => $points];
        if ($db->createSchemaManager()->tablesExist(['driver_performance_scoring_rules'])) {
            $saved = $db->fetchOne('SELECT rules_json FROM driver_performance_scoring_rules WHERE id = 1');
            if ($saved) {
                $decoded = json_decode((string)$saved, true);
                if (is_array($decoded)) {
                    if (isset($decoded['focus_messages']) && is_array($decoded['focus_messages'])) foreach ($focusDefaults as $key => $default) if (isset($decoded['focus_messages'][$key]) && is_string($decoded['focus_messages'][$key])) $focusMessages[$key] = $decoded['focus_messages'][$key];
                    if (isset($decoded['focus_headings']) && is_array($decoded['focus_headings'])) foreach ($focusHeadings as $key => $default) if (isset($decoded['focus_headings'][$key]) && is_string($decoded['focus_headings'][$key]) && trim($decoded['focus_headings'][$key]) !== '') $focusHeadings[$key] = $decoded['focus_headings'][$key];
                    foreach ($netradyneMultipliers as $key => $default) if (isset($decoded['netradyne_multipliers'][$key]) && is_numeric($decoded['netradyne_multipliers'][$key]) && (float)$decoded['netradyne_multipliers'][$key] >= 0 && (float)$decoded['netradyne_multipliers'][$key] <= 100) $netradyneMultipliers[$key] = (float)$decoded['netradyne_multipliers'][$key];
                    if (isset($decoded['scorecard_explanation']) && is_string($decoded['scorecard_explanation'])) $scorecardExplanation = $decoded['scorecard_explanation'];
                    foreach ($reportTitles as $key => $default) if (isset($decoded['report_titles'][$key]) && is_string($decoded['report_titles'][$key]) && trim($decoded['report_titles'][$key]) !== '') $reportTitles[$key] = $decoded['report_titles'][$key];
                    foreach ($scoreRules as $key => $default) if (isset($decoded[$key]['maximum'])) $scoreRules[$key]['maximum'] = (float)$decoded[$key]['maximum'];
                    if (isset($decoded['psb_threshold']) && is_numeric($decoded['psb_threshold'])) $psbThreshold = (float)$decoded['psb_threshold'];
                    if (isset($decoded['psb_points']) && is_numeric($decoded['psb_points'])) $psbPoints = (float)$decoded['psb_points'];
                    if (isset($decoded['psb_bands']) && is_array($decoded['psb_bands']) && count($decoded['psb_bands']) >= 1 && count($decoded['psb_bands']) <= 40) $psbBands = $decoded['psb_bands'];
                    if (isset($decoded['psb_colors']) && is_array($decoded['psb_colors'])) $psbColors = array_merge($psbColors, $decoded['psb_colors']);
                    if (isset($decoded['dwc_colors']) && is_array($decoded['dwc_colors'])) $dwcColors = array_merge($dwcColors, $decoded['dwc_colors']);
                    if (isset($decoded['dwc_bands']) && is_array($decoded['dwc_bands']) && count($decoded['dwc_bands']) >= 1 && count($decoded['dwc_bands']) <= 40) $dwcBands = $decoded['dwc_bands'];
                    if (isset($decoded['fico_colors']) && is_array($decoded['fico_colors'])) $ficoColors = array_merge($ficoColors, $decoded['fico_colors']);
                    if (isset($decoded['fico_bands']) && is_array($decoded['fico_bands']) && count($decoded['fico_bands']) >= 1 && count($decoded['fico_bands']) <= 40) $ficoBands = $decoded['fico_bands'];
                    if (isset($decoded['dpmo_bands']) && is_array($decoded['dpmo_bands']) && count($decoded['dpmo_bands']) >= 1 && count($decoded['dpmo_bands']) <= 40) $dpmoBands = $decoded['dpmo_bands'];
                    if (isset($decoded['pod_bands']) && is_array($decoded['pod_bands']) && count($decoded['pod_bands']) >= 1 && count($decoded['pod_bands']) <= 40) $podBands = $decoded['pod_bands'];
                    if (isset($decoded['dpmo_colors']) && is_array($decoded['dpmo_colors'])) $dpmoColors = array_merge($dpmoColors, $decoded['dpmo_colors']);
                    if (isset($decoded['cdf_bands']) && is_array($decoded['cdf_bands']) && count($decoded['cdf_bands']) >= 1 && count($decoded['cdf_bands']) <= 40) $cdfBands = $decoded['cdf_bands'];
                    if (isset($decoded['cdf_below']) && is_numeric($decoded['cdf_below'])) $cdfBelow = (float)$decoded['cdf_below'];
                    if (isset($decoded['cdf_below_points']) && is_numeric($decoded['cdf_below_points'])) $cdfBelowPoints = (float)$decoded['cdf_below_points'];
                    if (isset($decoded['cdf_colors']) && is_array($decoded['cdf_colors'])) $cdfColors = array_merge($cdfColors, $decoded['cdf_colors']);
                    if (isset($decoded['ce_bands']) && is_array($decoded['ce_bands']) && count($decoded['ce_bands']) >= 1 && count($decoded['ce_bands']) <= 40) $ceBands = $decoded['ce_bands'];
                    if (isset($decoded['ce_above_points']) && is_numeric($decoded['ce_above_points'])) $ceAbovePoints = (float)$decoded['ce_above_points'];
                    if (isset($decoded['ce_colors']) && is_array($decoded['ce_colors'])) $ceColors = array_merge($ceColors, $decoded['ce_colors']);
                    if (isset($decoded['cc_bands']) && is_array($decoded['cc_bands']) && count($decoded['cc_bands']) >= 1 && count($decoded['cc_bands']) <= 40) $ccBands = $decoded['cc_bands'];
                    if (isset($decoded['cc_colors']) && is_array($decoded['cc_colors'])) $ccColors = array_merge($ccColors, $decoded['cc_colors']);
                    if (isset($decoded['pod_colors']) && is_array($decoded['pod_colors'])) $podColors = array_merge($podColors, $decoded['pod_colors']);
                }
            }
        }
        $safetyMetrics = ['fico'=>'Safe Driving Metric (FICO / Ementor)','netSpeed'=>'Speeding Event Rate (Netradyne Violation)','netD'=>'Distraction Rate (Netradyne Violation)','netFd'=>'Following Distance Rate (Netradyne Violation)','netSs'=>'Sign Signal Rate (Netradyne Violation)','netTl'=>'Traffic Light Rate (Netradyne Violation)'];
        $reportMetrics = ['dcr'=>'Delivery Completion Rate (DCR)','ce'=>'Customer Escalation (CE)','cdf'=>'Customer Delivery Feedback (Negative Feedback)','dwc'=>'Delivery Workflow Compliance (DWC)','iadc'=>'IADC','pod'=>'Photo on Delivery (POD)','podBypass'=>'By Pass','podRejects'=>'Rejects','cc'=>'Contact Compliance (CC)','contactMade'=>'Contact Made','contactsMissed'=>'Contacts Missed','dpmo'=>'Delivery Success Conditions (DSC DPMO)','lor'=>'Lost on Road (LOR) DPMO','psb'=>'Pickup Success Behaviours (PSB Collection Fails)'];
        $focusTriggerDefaults = ['NONE'=>'NONE','FICO'=>'FICO','NET'=>'NET','DCR'=>'DCR','DPMO'=>'DPMO','POD'=>'POD','CE'=>'CE','DWC'=>'DWC','CC'=>'CC','NOA'=>'NOA','DEX'=>'DEX','PBS'=>'PBS'];
        $focusTriggers = $focusTriggerDefaults;
        foreach ($focusTriggers as $key => $default) {
            $candidate = $decoded['focus_triggers'][$key] ?? $default;
            if (is_string($candidate) && array_key_exists($candidate, $focusTriggerDefaults)) $focusTriggers[$key] = $candidate;
        }
        $focusOrder = array_keys($focusDefaults);
        $savedFocusOrder = $decoded['focus_order'] ?? null;
        if (is_array($savedFocusOrder)) $focusOrder = array_values(array_unique(array_merge(array_values(array_intersect($savedFocusOrder, $focusOrder)), $focusOrder)));
        $metricFocus = is_array($decoded['metric_focus'] ?? null) ? $decoded['metric_focus'] : [];
        $metricLabels = is_array($decoded['metric_labels'] ?? null) ? $decoded['metric_labels'] : [];
        $safetyOrder = array_keys($safetyMetrics);
        if (is_array($decoded['safety_order'] ?? null)) {
            $candidate = $decoded['safety_order'];
            if (count($candidate) === count($safetyMetrics) && count(array_unique($candidate)) === count($safetyMetrics) && !array_diff($candidate, $safetyOrder)) $safetyOrder = array_values($candidate);
        }
        $reportLayout = ['title'=>'DA WEEKLY QUALITY REPORT','safety'=>'Safety','delivery'=>'Delivery Quality & Standard Work Compliance','order'=>array_keys($reportMetrics),'indents'=>['podBypass'=>true,'podRejects'=>true]];
        if (is_array($decoded['report_layout'] ?? null)) {
            foreach (['title','safety','delivery'] as $key) {
                $value = $decoded['report_layout'][$key] ?? null;
                if (is_string($value) && trim($value) !== '' && mb_strlen($value) <= 120) $reportLayout[$key] = $value;
            }
            $savedIndents = $decoded['report_layout']['indents'] ?? null;
            if (is_array($savedIndents)) {
                foreach (array_keys(array_merge($reportMetrics, $safetyMetrics)) as $key) $reportLayout['indents'][$key] = ($savedIndents[$key] ?? false) === true;
            }
            $order = $decoded['report_layout']['order'] ?? null;
            if (is_array($order)) { $order = array_values(array_filter($order, static fn($key) => array_key_exists($key, $reportMetrics))); if (count(array_unique($order)) === count($order)) $reportLayout['order'] = array_values(array_unique(array_merge($order, array_keys($reportMetrics)))); }
        }
        if ($request->isMethod('POST') && $tab === 'report' && $request->request->get('rules_action') !== 'scorecard_text') {
            if (!$full) throw $this->createAccessDeniedException();
            if (!$csrfManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('report_layout', (string)$request->request->get('_token')))) throw $this->createAccessDeniedException('Invalid CSRF token.');
            foreach (['title','safety','delivery'] as $key) {
                $value = trim((string)$request->request->get($key, ''));
                if ($value === '' || mb_strlen($value) > 120) throw new \InvalidArgumentException('Report headings must be 1–120 characters.');
                $reportLayout[$key] = $value;
            }
            $order = $request->request->all('order');
            if (count($order) !== count($reportMetrics) || count(array_unique($order)) !== count($reportMetrics) || array_diff($order, array_keys($reportMetrics))) throw new \InvalidArgumentException('Invalid metric order.');
            $reportLayout['order'] = array_values($order);
            $postedIndents = $request->request->all('metric_indents');
            if (array_diff(array_keys($postedIndents), array_keys(array_merge($reportMetrics, $safetyMetrics)))) throw new \InvalidArgumentException('Invalid metric indentation.');
            $reportLayout['indents'] = [];
            foreach (array_keys(array_merge($reportMetrics, $safetyMetrics)) as $key) $reportLayout['indents'][$key] = ($postedIndents[$key] ?? null) === '1';
            $postedSafetyOrder = $request->request->all('safety_order');
            if (count($postedSafetyOrder) !== count($safetyMetrics) || count(array_unique($postedSafetyOrder)) !== count($safetyMetrics) || array_diff($postedSafetyOrder, array_keys($safetyMetrics))) throw new \InvalidArgumentException('Invalid safety metric order.');
            $safetyOrder = array_values($postedSafetyOrder);
            $decoded['safety_order'] = $safetyOrder;
            $submittedMetricLabels = $request->request->all('metric_labels');
            foreach (array_merge($reportMetrics, $safetyMetrics) as $metricKey => $defaultLabel) {
                $label = trim((string)($submittedMetricLabels[$metricKey] ?? $defaultLabel));
                if ($label === '' || mb_strlen($label) > 120) throw new \InvalidArgumentException('Invalid metric name.');
                $metricLabels[$metricKey] = $label;
            }
            $decoded['metric_labels'] = $metricLabels;
            $submittedMetricFocus = $request->request->all('metric_focus');
            foreach (array_merge($reportMetrics, $safetyMetrics) as $metricKey => $metricName) {
                $fields = $submittedMetricFocus[$metricKey] ?? [];
                if (!is_array($fields)) throw new \InvalidArgumentException('Invalid metric focus settings.');
                $heading = trim((string)($fields['heading'] ?? $metricName));
                $message = trim((string)($fields['message'] ?? ''));
                if ($heading === '' || mb_strlen($heading) > 80 || mb_strlen($message) > 1200) throw new \InvalidArgumentException('Invalid metric focus heading or message.');
                $metricFocus[$metricKey] = ['heading' => $heading, 'message' => $message];
            }
            $decoded['metric_focus'] = $metricFocus;
            $decoded['report_layout'] = $reportLayout;
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode($decoded, JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=report&week='.$weekStart.'&saved=1');
        }
        // Row colours are keyed by threshold, so sorting does not change their association.
        $colourBands = ['fico'=>$ficoBands,'dwc'=>$dwcBands,'psb'=>$psbBands,'dpmo'=>$dpmoBands,'pod'=>$podBands,'cc'=>$ccBands,'ce'=>$ceBands,'cdf'=>$cdfBands];
        $colourPalettes = ['fico'=>$ficoColors,'dwc'=>$dwcColors,'psb'=>$psbColors,'dpmo'=>$dpmoColors,'pod'=>$podColors,'cc'=>$ccColors,'ce'=>$ceColors,'cdf'=>$cdfColors];
        $conditionColours = is_array($decoded['condition_colours'] ?? null) ? $decoded['condition_colours'] : [];
        $savedRowColourOverrides = $conditionColours;
        foreach ($colourBands as $metric => $bands) {
            foreach ($bands as [$threshold, $points]) {
                $key = (string)(float)$threshold;
                if (isset($conditionColours[$metric][$key]) && is_string($conditionColours[$metric][$key]) && preg_match('/^#[0-9a-fA-F]{6}$/D', $conditionColours[$metric][$key])) continue;
                $p = $colourPalettes[$metric]; $v = (float)$threshold;
                $shade = match ($metric) {
                    'fico' => $v < $p['red_below'] ? 'red' : ($v < $p['amber_below'] ? 'amber' : 'green'),
                    'dwc','pod','cc' => $v < $p['red_below'] ? 'red' : ($v <= $p['green_above'] ? 'amber' : 'green'),
                    'dpmo' => $v <= $p['green_at_or_below'] ? 'green' : ($v <= $p['red_above'] ? 'amber' : 'red'),
                    'psb','ce' => $v == 0 ? 'green' : 'red',
                    'cdf' => (float)$points >= 5 ? 'green' : ((float)$points <= 0 ? 'red' : 'amber')
                };
                $conditionColours[$metric][$key] = $metric === 'ce' ? $p[($shade === 'green' ? 'zero' : 'positive').'_color'] : $p[$shade.'_color'];
            }
        }
        $saveRowColours = static function (string $metric, array $thresholds, array $submitted, array &$conditionColours): void {
            if (count($thresholds) !== count($submitted)) throw new \InvalidArgumentException('Choose a colour for each scoring condition.');
            $new = [];
            foreach ($thresholds as $i => $threshold) {
                $hex = $submitted[$i] ?? null;
                if (!is_string($hex) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) throw new \InvalidArgumentException('Invalid condition colour.');
                $new[(string)(float)$threshold] = $hex;
            }
            $conditionColours[$metric] = $new;
        };

        if ($request->isMethod('POST') && $request->request->get('score_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('performance_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $values = $request->request->all('maximum');
            $total = 0;
            foreach ($scoreRules as $key => &$rule) {
                $raw = $values[$key] ?? null;
                if (!is_scalar($raw) || !is_numeric($raw) || (float)$raw < 0 || (float)$raw > 100) throw new \InvalidArgumentException('Each category maximum must be between 0 and 100.');
                $rule['maximum'] = (float)$raw;
                $total += $rule['maximum'];
            }
            unset($rule);
            if (abs($total - 100) > 0.001) {
                $this->addFlash('score_error', 'The category weights currently add up to '.number_format($total, 1).' points. Please adjust them so the total is exactly 100.');
                return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&score_error=1');
            }
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($decoded, $scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=weekly&week='.$weekStart.'&rules_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('netradyne_rules_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('netradyne_multiplier_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $submitted = $request->request->all('netradyne_multiplier');
            foreach ($netradyneMultipliers as $key => $default) {
                $raw = $submitted[$key] ?? null;
                if (!is_scalar($raw) || !is_numeric($raw) || !is_finite((float)$raw) || (float)$raw < 0 || (float)$raw > 100) {
                    $this->addFlash('score_error', 'Netradyne multipliers must be numbers between 0 and 100.');
                    return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
                }
                $netradyneMultipliers[$key] = (float)$raw;
            }
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($decoded, ['netradyne_multipliers'=>$netradyneMultipliers]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&netradyne_rules_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('fico_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('fico_color_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $red = filter_var($request->request->get('red_below'), FILTER_VALIDATE_FLOAT);
            $amber = filter_var($request->request->get('amber_below'), FILTER_VALIDATE_FLOAT);
            if ($red === false || $amber === false || $red < 0 || $amber <= $red || $amber > 850) throw new \InvalidArgumentException('Enter valid FICO thresholds between 0 and 850, with amber above red.');
            $newColours = [];
            foreach (['red','amber','green'] as $shade) {
                $hex = (string)$request->request->get('fico_'.$shade.'_color', '');
                if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) throw new \InvalidArgumentException('Select valid FICO colours.');
                $newColours[$shade.'_color'] = $hex;
            }
            $ficoColors = array_merge($ficoColors, $newColours, ['red_below' => $red, 'amber_below' => $amber]);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($decoded, $scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&fico_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('fico_formula_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('fico_formula_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $thresholds = $request->request->all('fico_threshold');
            $points = $request->request->all('fico_points');
            $bands = [];
            $ficoMaximum = (float)$scoreRules['fico']['maximum'];
            if ($ficoMaximum <= 0) { $this->addFlash('score_error', 'Set the FICO/EMENTOR maximum above zero before editing its formula.'); return $this->redirect('/driver-performance?tab=rules&week='.$weekStart); }
            if (count($thresholds) < 1 || count($thresholds) > 40 || count($thresholds) !== count($points)) throw new \InvalidArgumentException('Enter between 1 and 40 FICO formula rows.');
            foreach (array_keys($thresholds) as $i) {
                $t = $thresholds[$i] ?? null;
                $v = $points[$i] ?? null;
                if (!is_scalar($t) || !preg_match('/^\d{1,3}$/', (string)$t) || (int)$t > 850 || (int)$t < 0
                    || !is_scalar($v) || !is_numeric($v) || (float)$v < 0 || (float)$v > $ficoMaximum)
                    throw new \InvalidArgumentException('FICO thresholds must descend strictly and points must be between 0 and the FICO category maximum.');
                $bands[] = [(int)$t, (float)$v];
            }
            usort($bands, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
            foreach ($bands as $i => $band) {
                if ($i > 0 && $band[0] === $bands[$i - 1][0]) {
                    return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&fico_formula_error=duplicate');
                }
            }
            $ficoBands = $bands;
            $saveRowColours('fico', $thresholds, $request->request->all('condition_color'), $conditionColours);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($decoded, $scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&fico_formula_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('dwc_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('dwc_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $red = filter_var($request->request->get('dwc_red'), FILTER_VALIDATE_FLOAT);
            $green = filter_var($request->request->get('dwc_green'), FILTER_VALIDATE_FLOAT);
            if ($red === false || $green === false || $red < 0 || $green <= $red || $green > 100) {
                $this->addFlash('dwc_error', 'Enter valid colour thresholds: red below green, both between 0 and 100.');
                return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
            }
            $newColours = [];
            foreach (['red', 'amber', 'green'] as $shade) {
                $hex = (string)$request->request->get('dwc_'.$shade.'_color', '');
                if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) throw new \InvalidArgumentException('Select valid DWC colours.');
                $newColours[$shade.'_color'] = $hex;
            }
            $dwcColors = array_merge($dwcColors, $newColours, ['red_below' => $red, 'green_above' => $green]);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($decoded, $scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&dwc_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('dwc_formula_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('dwc_formula_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $thresholds = $request->request->all('dwc_threshold');
            $points = $request->request->all('dwc_points');
            $maximum = (float)$scoreRules['dwc']['maximum'];
            $bands = [];
            if (count($thresholds) < 1 || count($thresholds) > 40 || count($thresholds) !== count($points)) {
                $this->addFlash('dwc_error', 'Enter between 1 and 40 DWC formula rows.');
                return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
            }
            foreach (array_keys($thresholds) as $i) {
                $t = $thresholds[$i]; $v = $points[$i] ?? null;
                if (!is_scalar($t) || !is_numeric($t) || (float)$t < 0 || (float)$t > 100 || !is_scalar($v) || !is_numeric($v) || (float)$v < 0 || (float)$v > $maximum) {
                    $this->addFlash('dwc_error', 'DWC thresholds must be 0–100% and points must be between zero and the saved DWC maximum.');
                    return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
                }
                $bands[] = [(float)$t, (float)$v];
            }
            usort($bands, static fn($a,$b) => $b[0] <=> $a[0]);
            foreach ($bands as $i => $band) if ($i && $band[0] === $bands[$i-1][0]) {
                $this->addFlash('dwc_error', 'Duplicate DWC thresholds are not allowed.');
                return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
            }
            $dwcBands = $bands;
            $saveRowColours('dwc', $thresholds, $request->request->all('condition_color'), $conditionColours);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($decoded, $scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&dwc_formula_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('psb_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('psb_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $thresholds = $request->request->all('psb_threshold');
            $points = $request->request->all('psb_points');
            if (count($thresholds) < 1 || count($thresholds) > 40 || count($thresholds) !== count($points)) {
                $this->addFlash('psb_error', 'Enter between 1 and 40 PSB conditions.');
                return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
            }
            $bands = [];
            foreach ($thresholds as $i => $threshold) {
                $awarded = $points[$i] ?? null;
                if (!is_scalar($threshold) || !is_numeric($threshold) || (float)$threshold < 0 || !is_scalar($awarded) || !is_numeric($awarded) || (float)$awarded < 0 || (float)$awarded > (float)$scoreRules['psb']['maximum']) {
                    $this->addFlash('psb_error', 'Enter non-negative thresholds and points within the PSB maximum.');
                    return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
                }
                $bands[] = [(float)$threshold, (float)$awarded];
            }
            usort($bands, static fn($a, $b) => $a[0] <=> $b[0]);
            foreach ($bands as $i => $band) if ($i && $band[0] === $bands[$i - 1][0]) {
                $this->addFlash('psb_error', 'Duplicate PSB thresholds are not allowed.');
                return $this->redirect('/driver-performance?tab=rules&week='.$weekStart);
            }
            $psbBands = $bands;
            [$psbThreshold, $psbPoints] = $psbBands[0];
            foreach (['green','red'] as $shade) {
                $hex = (string)$request->request->get('psb_'.$shade.'_color', '');
                if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) throw new \InvalidArgumentException('Select valid PSB colours.');
                $psbColors[$shade.'_color'] = $hex;
            }
            $saveRowColours('psb', $thresholds, $request->request->all('condition_color'), $conditionColours);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($decoded, $scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&psb_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && in_array($request->request->get('metric_action'), ['dpmo', 'pod', 'cc', 'cdf'], true)) {
            $metric = (string)$request->request->get('metric_action');
            if (!$full || !$this->isCsrfTokenValid($metric.'_formula_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $thresholds = $request->request->all('metric_threshold');
            $points = $request->request->all('metric_points');
            $maximum = (float)$scoreRules[$metric === 'dpmo' ? 'dsc_dpmo' : ($metric === 'cdf' ? 'cdf_dpmo' : $metric)]['maximum'];
            if (count($thresholds) < 1 || count($thresholds) > 40 || count($thresholds) !== count($points)) throw new \InvalidArgumentException('Enter 1–40 scoring rows.');
            $bands = [];
            foreach ($thresholds as $i => $t) {
                $v = $points[$i] ?? null;
                if (!is_scalar($t) || !is_numeric($t) || (float)$t < 0 || (in_array($metric, ['pod','cc'], true) && (float)$t > 100) || !is_scalar($v) || !is_numeric($v) || (float)$v < 0 || (float)$v > $maximum) throw new \InvalidArgumentException('Invalid scoring threshold or points.');
                $bands[] = [(float)$t, (float)$v];
            }
            usort($bands, static fn($a,$b) => $b[0] <=> $a[0]);
            foreach ($bands as $i => $band) if ($i && $band[0] === $bands[$i-1][0]) throw new \InvalidArgumentException('Duplicate thresholds are not allowed.');
            $low = filter_var($request->request->get('metric_low'), FILTER_VALIDATE_FLOAT);
            $high = filter_var($request->request->get('metric_high'), FILTER_VALIDATE_FLOAT);
            if ($low === false || $high === false || $low < 0 || $high <= $low || (in_array($metric, ['pod','cc'], true) && $high > 100)) throw new \InvalidArgumentException('Invalid colour thresholds.');
            $newColours = [];
            foreach (['green', 'amber', 'red'] as $shade) {
                $hex = (string)$request->request->get('metric_'.$shade.'_color', '');
                if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) throw new \InvalidArgumentException('Choose valid colour values.');
                $newColours[$shade.'_color'] = $hex;
            }
            if ($metric === 'cdf') {
                $cdfBands = $bands;
                $cdfColors = array_merge($newColours, ['green_below'=>$low,'red_above'=>$high]);
                $below = $request->request->get('cdf_below');
                $belowPoints = $request->request->get('cdf_below_points');
                if (!is_scalar($below) || !is_numeric($below) || (float)$below < 0 || !is_scalar($belowPoints) || !is_numeric($belowPoints) || (float)$belowPoints < 0 || (float)$belowPoints > $maximum) throw new \InvalidArgumentException('Invalid negative feedback fallback.');
                $cdfBelow = (float)$below;
                $cdfBelowPoints = (float)$belowPoints;
            }
            if ($metric === 'cc') { $ccBands = $bands; $ccColors = array_merge($newColours, ['red_below' => $low, 'green_above' => $high]); }
            elseif ($metric === 'pod') { $podBands = $bands; $podColors = array_merge($newColours, ['red_below' => $low, 'green_above' => $high]); }
            elseif ($metric === 'dpmo') { $dpmoBands = $bands; $dpmoColors = array_merge($newColours, ['green_at_or_below' => $low, 'red_above' => $high]); }
            $saveRowColours($metric, $thresholds, $request->request->all('condition_color'), $conditionColours);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($decoded, $scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&metric_saved='.$metric);
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('ce_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('ce_formula_rules', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $thresholds = $request->request->all('ce_threshold');
            $points = $request->request->all('ce_points');
            $max = (float)$scoreRules['ce']['maximum'];
            if (count($thresholds) < 1 || count($thresholds) > 40 || count($thresholds) !== count($points)) throw new \InvalidArgumentException('Enter 1–40 CE conditions.');
            $bands = [];
            foreach ($thresholds as $i => $threshold) {
                $awarded = $points[$i] ?? null;
                if (!is_scalar($threshold) || !is_numeric($threshold) || (float)$threshold < 0 || !is_scalar($awarded) || !is_numeric($awarded) || (float)$awarded < 0 || (float)$awarded > $max) throw new \InvalidArgumentException('Invalid CE threshold or points.');
                $bands[] = [(float)$threshold, (float)$awarded];
            }
            usort($bands, static fn($a,$b) => $a[0] <=> $b[0]);
            foreach ($bands as $i => $band) if ($i && $band[0] === $bands[$i-1][0]) throw new \InvalidArgumentException('Duplicate CE thresholds.');
            $above = $request->request->get('ce_above_points');
            if (!is_scalar($above) || !is_numeric($above) || (float)$above < 0 || (float)$above > $max) throw new \InvalidArgumentException('Invalid CE fallback points.');
            $colors = [];
            foreach (['zero','positive','missing'] as $shade) {
                $hex = (string)$request->request->get('ce_'.$shade.'_color');
                if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) throw new \InvalidArgumentException('Invalid CE colour.');
                $colors[$shade.'_color'] = $hex;
            }
            $ceBands = $bands;
            $ceAbovePoints = (float)$above;
            $ceColors = $colors;
            $saveRowColours('ce', $thresholds, $request->request->all('condition_color'), $conditionColours);
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode(array_merge($decoded, $scoreRules, ['fico_colors' => $ficoColors, 'fico_bands' => $ficoBands, 'dwc_colors' => $dwcColors, 'dwc_bands' => $dwcBands, 'psb_threshold' => $psbThreshold, 'psb_points' => $psbPoints, 'psb_colors' => $psbColors, 'psb_bands' => $psbBands, 'dpmo_bands' => $dpmoBands, 'dpmo_colors' => $dpmoColors, 'pod_bands' => $podBands, 'pod_colors' => $podColors, 'cc_bands' => $ccBands, 'cc_colors' => $ccColors, 'ce_bands' => $ceBands, 'ce_above_points' => $ceAbovePoints, 'ce_colors' => $ceColors, 'cdf_bands' => $cdfBands, 'cdf_below' => $cdfBelow, 'cdf_below_points' => $cdfBelowPoints, 'cdf_colors' => $cdfColors, 'condition_colours' => $conditionColours, 'report_titles' => $reportTitles, 'focus_messages' => $focusMessages, 'scorecard_explanation' => $scorecardExplanation]), JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&ce_saved=1');
        }
        $notice = '';
        if ($request->isMethod('POST') && $tab === 'weekly' && $request->request->get('netradyne_action') === 'save') {
            if (!$full || !$this->isCsrfTokenValid('netradyne_weekly', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $id = strtoupper(trim((string)$request->request->get('transporter_id', '')));
            $driver = $em->getRepository(User::class)->findOneBy(['transporterId' => $id]);
            if (!$driver || !in_array('ROLE_DRIVER', $driver->getRoles(), true)) throw new \InvalidArgumentException('Unknown driver Transporter ID.');
            $values = [];
            foreach (['d','fd','ss','speed','tl'] as $field) {
                $value = $request->request->get($field);
                if (!is_scalar($value) || !preg_match('/^-?\\d{1,5}$/', (string)$value) || (int)$value < -99999) throw new \InvalidArgumentException('Enter a whole number from -99999 to 99999 for each Netradyne field.');
                $values[] = (int)$value;
            }
            $db->executeStatement('INSERT INTO driver_netradyne_weekly (week_start, transporter_id, d, fd, ss, speed, tl, updated_by, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE d=VALUES(d), fd=VALUES(fd), ss=VALUES(ss), speed=VALUES(speed), tl=VALUES(tl), updated_by=VALUES(updated_by), updated_at=NOW()', array_merge([$weekStart, $id], $values, [$this->getUser()->getId()]));
            if ($request->request->get('autosave') === '1') return new \Symfony\Component\HttpFoundation\JsonResponse(['saved' => true]);
            return $this->redirect('/driver-performance?tab=weekly&week='.$weekStart.'&netradyne_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'report' && $request->request->get('rules_action') === 'scorecard_text') {
            if (!$full || !$this->isCsrfTokenValid('scorecard_text', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $submitted = $request->request->all('focus_messages');
            foreach ($focusDefaults as $key => $default) {
                $value = trim((string)($submitted[$key] ?? ''));
                if (mb_strlen($value) > 1200) throw new \InvalidArgumentException('Focus text exceeds 1200 characters.');
                $focusMessages[$key] = $value;
            }
            $submittedHeadings = $request->request->all('focus_headings');
            foreach ($focusHeadings as $key => $default) {
                $heading = trim((string)($submittedHeadings[$key] ?? $default));
                if ($heading === '' || mb_strlen($heading) > 80) throw new \InvalidArgumentException('Focus heading must be between 1 and 80 characters.');
                $focusHeadings[$key] = $heading;
            }
            $submittedTriggers = $request->request->all('focus_triggers');
            foreach ($focusTriggers as $key => $default) {
                $candidate = $submittedTriggers[$key] ?? $default;
                if (!is_string($candidate) || !array_key_exists($candidate, $focusTriggerDefaults)) throw new \InvalidArgumentException('Invalid linked metric.');
                $focusTriggers[$key] = $candidate;
            }
            $submittedOrder = $request->request->all('focus_order');
            if (count($submittedOrder) !== count($focusOrder) || count(array_unique($submittedOrder)) !== count($focusOrder) || array_diff($submittedOrder, $focusOrder)) throw new \InvalidArgumentException('Invalid focus order.');
            $focusOrder = array_values($submittedOrder);
            $scorecardExplanation = trim((string)$request->request->get('scorecard_explanation', ''));
            if (mb_strlen($scorecardExplanation) > 12000) throw new \InvalidArgumentException('Scorecard explanation exceeds 12000 characters.');
            $savedSettings = is_array($decoded) ? $decoded : [];
            $savedSettings['focus_messages'] = $focusMessages;
            $savedSettings['focus_headings'] = $focusHeadings;
            $savedSettings['focus_triggers'] = $focusTriggers;
            $savedSettings['focus_order'] = $focusOrder;
            $scorecardExplanation = \App\Controller\ScorecardRichText::sanitize($scorecardExplanation);
            $savedSettings['scorecard_explanation'] = $scorecardExplanation;
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode($savedSettings, JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=report&week='.$weekStart.'&scorecard_text_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'rules' && $request->request->get('rules_action') === 'report_titles') {
            if (!$full || !$this->isCsrfTokenValid('report_titles', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            $submitted = $request->request->all('report_titles');
            foreach (PerformanceReportTitle::DEFAULTS as $key => $default) {
                $value = trim((string)($submitted[$key] ?? ''));
                if ($value === '' || mb_strlen($value) > 150) {
                    return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&report_titles_error=1');
                }
                $reportTitles[$key] = $value;
            }
            $savedSettings = is_array($decoded) ? $decoded : [];
            $savedSettings['report_titles'] = $reportTitles;
            $db->executeStatement('INSERT INTO driver_performance_scoring_rules (id, rules_json, updated_by, updated_at) VALUES (1, ?, ?, NOW()) ON DUPLICATE KEY UPDATE rules_json=VALUES(rules_json), updated_by=VALUES(updated_by), updated_at=NOW()', [json_encode($savedSettings, JSON_THROW_ON_ERROR), $this->getUser()->getId()]);
            return $this->redirect('/driver-performance?tab=rules&week='.$weekStart.'&report_titles_saved=1');
        }
        if ($request->isMethod('POST') && $tab === 'dwc') {
            if (!$full || !$this->isCsrfTokenValid('dwc_import', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            if (in_array($request->request->get('dwc_action'), ['ignore', 'delete'], true)) {
                if ($request->request->get('dwc_action') === 'delete') {
                $ids = array_unique(array_map('strtoupper', $request->request->all('delete_ids')));
                foreach ($ids as $id) {
                    if (preg_match('/^[A-Z0-9]{1,60}$/D', $id)) $db->executeStatement('DELETE FROM driver_dwc_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=dwc&week='.$weekStart.'&unmatched_deleted=1');
            }
            $ids = array_map('strtoupper', $request->request->all('ignore_ids'));
                foreach ($db->fetchFirstColumn('SELECT transporter_id FROM driver_dwc_unmatched WHERE week_start = ?', [$weekStart]) as $id) {
                    $db->executeStatement('UPDATE driver_dwc_unmatched SET ignored = ? WHERE week_start = ? AND transporter_id = ?', [in_array($id, $ids, true) ? 1 : 0, $weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=dwc&week='.$weekStart);
            }
            try {
                $sourceRows = (new PerformanceReportReader())->read($request->files->get('dwc_file'), (string)$request->request->get('dwc_paste', ''));
                PerformanceReportTitle::requireTitle($sourceRows, $reportTitles['dwc'], 'DWC/IADC');
                $entries = [];
                foreach ($sourceRows as $cells) {
                    $offset = count($cells) >= 4 && ctype_digit(trim((string)$cells[0])) ? 1 : 0;
                    if (count($cells) < $offset + 3) continue;
                    $id = strtoupper(preg_replace('/\s+/', '', (string)$cells[$offset]));
                    if (!preg_match('/^[A-Z0-9]{8,40}$/', $id) || !preg_match('/[A-Z]/', $id)) continue;
                    $values = [];
                    foreach ([$offset + 1, $offset + 2] as $i) {
                        $raw = trim((string)$cells[$i]);
                        if (in_array($raw, ['', '-'], true)) { $values[] = 0.0; continue; }
                        if (!preg_match('/^(100(?:\.0+)?|\d{1,2}(?:\.\d+)?)\s*%?$/', $raw, $match)) continue 2;
                        $values[] = (float)$match[1];
                    }
                    if (!isset($entries[$id])) $entries[$id] = $values;
                }
                if (!$entries) throw new \InvalidArgumentException('No valid DWC/IADC rows found. Expected Transporter ID, DWC %, IADC %.');
                $db->beginTransaction();
                try {
                    $db->executeStatement('DELETE FROM driver_dwc_iadc WHERE week_start = ?', [$weekStart]);
                    $matched = 0; $unmatched = []; $position = 0;
                    foreach ($entries as $id => [$dwc, $iadc]) {
                        ++$position;
                        $driver = $em->getRepository(User::class)->findOneBy(['transporterId' => $id]);
                        if (!$driver || !in_array('ROLE_DRIVER', $driver->getRoles(), true)) { $unmatched[] = $id; $db->executeStatement('INSERT INTO driver_dwc_unmatched (week_start, transporter_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE transporter_id=VALUES(transporter_id)', [$weekStart, $id]); continue; }
                        $db->executeStatement('DELETE FROM driver_dwc_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                        $db->executeStatement('INSERT INTO driver_dwc_iadc (week_start, transporter_id, report_position, dwc_percent, iadc_percent) VALUES (?, ?, ?, ?, ?)', [$weekStart, $id, $position, $dwc, $iadc]);
                        ++$matched;
                    }
                    $db->commit();
                } catch (\Throwable $error) { $db->rollBack(); throw $error; }
                $notice = '<p class="perf-style-3f8a2ca06b">Saved '.$matched.' DWC/IADC results.</p>';
                if ($unmatched) $notice .= '<p class="perf-style-2e86f203a8">Unmatched Transporter IDs (not imported): '.htmlspecialchars(implode(', ', $unmatched), ENT_QUOTES, 'UTF-8').'. Create drivers and reimport if required.</p>';
            } catch (\InvalidArgumentException $error) {
                $notice = '<p class="perf-style-2e86f203a8">'.htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8').'</p>';
            }
        }
        if ($request->isMethod('POST') && $tab === 'dsp') {
            if (!$full || !$this->isCsrfTokenValid('dsp_import', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            if (in_array($request->request->get('dsp_action'), ['ignore', 'delete'], true)) {
                if ($request->request->get('dsp_action') === 'delete') {
                $ids = array_unique(array_map('strtoupper', $request->request->all('delete_ids')));
                foreach ($ids as $id) {
                    if (preg_match('/^[A-Z0-9]{1,60}$/D', $id)) $db->executeStatement('DELETE FROM driver_dsp_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=dsp&week='.$weekStart.'&unmatched_deleted=1');
            }
            $ids = array_map('strtoupper', $request->request->all('ignore_ids'));
                foreach ($db->fetchFirstColumn('SELECT transporter_id FROM driver_dsp_unmatched WHERE week_start = ?', [$weekStart]) as $id) {
                    $db->executeStatement('UPDATE driver_dsp_unmatched SET ignored = ? WHERE week_start = ? AND transporter_id = ?', [in_array($id, $ids, true) ? 1 : 0, $weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=dsp&week='.$weekStart);
            }
            try {
                $entries = (new DspSummaryReader())->read($request->files->get('dsp_file'), (string)$request->request->get('dsp_paste', ''), $reportTitles['dsp']);
                $matched = 0; $unknown = [];
                $db->beginTransaction();
                try {
                    // Replace this week's report atomically so old rows do not linger.
                    $db->executeStatement('DELETE FROM driver_dsp_summary WHERE week_start = ?', [$weekStart]);
                    $position = 0;
                    foreach ($entries as $id => $values) {
                        ++$position;
                        $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $id]);
                        if (!$user || !in_array('ROLE_DRIVER', $user->getRoles(), true)) {
                            $unknown[] = $id;
                            $db->executeStatement('INSERT INTO driver_dsp_unmatched (week_start, transporter_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE transporter_id=VALUES(transporter_id)', [$weekStart, $id]);
                            continue;
                        }
                        $metrics = [];
                        foreach ($values as $v) $metrics[] = in_array(trim((string)$v), ['', '-'], true) ? 0.0 : (float)str_replace([',', '%'], '', $v);
                        $db->executeStatement('INSERT INTO driver_dsp_summary (week_start, transporter_id, report_position, delivered, dcr, dsc_dpmo, lor_dpmo, pod, cc, ce, cdf_dpmo, psb) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge([$weekStart, $id, $position], $metrics));
                        $db->executeStatement('DELETE FROM driver_dsp_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                        ++$matched;
                    }
                    $db->commit();
                } catch (\Throwable $e) { $db->rollBack(); throw $e; }
                $notice = '<p class="perf-style-3f8a2ca06b">Imported '.$matched.' DSP driver results; '.count($unknown).' unmatched IDs.</p>';
            } catch (InvalidArgumentException $e) {
                $notice = '<p class="perf-style-2e86f203a8">'.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</p>';
            }
        }
        if ($request->isMethod('POST') && $tab === 'pod') {
            if (!$full || !$this->isCsrfTokenValid('pod_import', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            if (in_array($request->request->get('pod_action'), ['ignore', 'delete'], true)) {
                if ($request->request->get('pod_action') === 'delete') {
                $ids = array_unique(array_map('strtoupper', $request->request->all('delete_ids')));
                foreach ($ids as $id) {
                    if (preg_match('/^[A-Z0-9]{1,60}$/D', $id)) $db->executeStatement('DELETE FROM driver_pod_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=pod&week='.$weekStart.'&unmatched_deleted=1');
            }
            $ids = array_map('strtoupper', $request->request->all('ignore_ids'));
                foreach ($db->fetchFirstColumn('SELECT transporter_id FROM driver_pod_unmatched WHERE week_start = ?', [$weekStart]) as $id) {
                    $db->executeStatement('UPDATE driver_pod_unmatched SET ignored = ? WHERE week_start = ? AND transporter_id = ?', [in_array($id, $ids, true) ? 1 : 0, $weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=pod&week='.$weekStart);
            }
            try {
                $file = $request->files->get('pod_file');
                if (!$file && trim((string)$request->request->get('pod_paste', '')) === '') throw new InvalidArgumentException('Choose a report file or paste report data.');
                $entries = (new PodReportReader())->read($file, (string)$request->request->get('pod_paste', ''), $reportTitles['pod']);
                $matched = 0;
                $unknown = [];
                $db->beginTransaction();
                try {
                    $position = 0;
                    foreach ($entries as $id => $values) {
                        ++$position;
                        $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $id]);
                        if (!$user || !in_array('ROLE_DRIVER', $user->getRoles(), true)) {
                            $unknown[] = $id;
                            $db->executeStatement('INSERT INTO driver_pod_unmatched (week_start, transporter_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE transporter_id=VALUES(transporter_id)', [$weekStart, $id]);
                            continue;
                        }
                        ++$matched;
                        $db->executeStatement('INSERT INTO driver_pod_report (week_start, transporter_id, report_position, opportunities, success, bypass, rejects, blurry_photo, no_package, package_in_car, package_too_close, photo_too_dark) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE report_position=VALUES(report_position), opportunities=VALUES(opportunities), success=VALUES(success), bypass=VALUES(bypass), rejects=VALUES(rejects), blurry_photo=VALUES(blurry_photo), no_package=VALUES(no_package), package_in_car=VALUES(package_in_car), package_too_close=VALUES(package_too_close), photo_too_dark=VALUES(photo_too_dark)', array_merge([$weekStart, $id, $position], $values));
                        $db->executeStatement('DELETE FROM driver_pod_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                    }
                    $db->commit();
                } catch (\Throwable $e) { $db->rollBack(); throw $e; }
                $notice = '<p class="perf-style-3f8a2ca06b">Saved '.$matched.' POD driver results. '.count($unknown).' unmatched IDs.</p>';
            } catch (InvalidArgumentException $e) {
                $notice = '<p class="perf-style-2e86f203a8">'.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8').'</p>';
            }
        }
        if ($request->isMethod('POST') && in_array($request->request->get('cc_action'), ['ignore', 'delete'], true)) {
            if (!$full || !$this->isCsrfTokenValid('cc_import', (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            if ($request->request->get('cc_action') === 'delete') {
                $ids = array_unique(array_map('strtoupper', $request->request->all('delete_ids')));
                foreach ($ids as $id) {
                    if (preg_match('/^[A-Z0-9]{1,60}$/D', $id)) $db->executeStatement('DELETE FROM driver_cc_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                }
                return $this->redirect('/driver-performance?tab=cc&week='.$weekStart.'&unmatched_deleted=1');
            }
            $ids = array_map('strtoupper', $request->request->all('ignore_ids'));
            foreach ($db->fetchFirstColumn('SELECT transporter_id FROM driver_cc_unmatched WHERE week_start = ?', [$weekStart]) as $id) {
                $db->executeStatement('UPDATE driver_cc_unmatched SET ignored = ? WHERE week_start = ? AND transporter_id = ?', [in_array($id, $ids, true) ? 1 : 0, $weekStart, $id]);
            }
            return $this->redirect('/driver-performance?tab=cc&week='.$weekStart);
        }
        if ($request->isMethod('POST') && $tab === 'cc') {
            if (!$full) throw $this->createAccessDeniedException();
            if (!$this->isCsrfTokenValid('cc_import', (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
            try {
                $reader = new PerformanceReportReader();
                $parsedRows = $reader->read($request->files->get('cc_file'), (string)$request->request->get('cc_html', ''));
            } catch (InvalidArgumentException $error) {
                $parsedRows = [];
                $notice = '<p class="perf-style-2e86f203a8">'.htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8').'</p>';
            }
            if ($parsedRows && !PerformanceReportTitle::matches($parsedRows, $reportTitles['cc'])) {
                $notice = '<p class="perf-style-2e86f203a8">Incorrect report type. Expected Contact Compliance report containing &quot;'.htmlspecialchars($reportTitles['cc'], ENT_QUOTES, 'UTF-8').'&quot;. Existing data was not changed.</p>';
                $parsedRows = [];
            }
            // Match DSP: validate the configured title and actual numeric driver rows.
            $entries = [];
            foreach ($parsedRows as $cells) {
                $offset = count($cells) >= 5 && ctype_digit(trim((string)$cells[0])) ? 1 : 0;
                if (count($cells) < $offset + 4) continue;
                $id = strtoupper(preg_replace('/\s+/', '', $cells[$offset]));
                if (!preg_match('/^[A-Z0-9]{8,40}$/', $id) || !preg_match('/[A-Z]/', $id)) continue;
                $cells = array_slice($cells, $offset);
                foreach ([1, 2, 3] as $i) if (in_array(trim((string)$cells[$i]), ['', '-'], true)) $cells[$i] = $i === 3 ? '0%' : '0';
                if (!ctype_digit(str_replace(',', '', $cells[1])) || !ctype_digit(str_replace(',', '', $cells[2]))) continue;
                $addresses = (int) str_replace(',', '', $cells[1]);
                $contacts = (int) str_replace(',', '', $cells[2]);
                if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*%$/', $cells[3], $match)) continue;
                $rate = (float)$match[1];
                if ($rate > 100 || $contacts > $addresses || isset($entries[$id])) continue;
                $entries[$id] = [$addresses, $contacts, $rate];
            }
            if (!$entries) {
                if ($notice === '') $notice = '<p class="perf-style-2e86f203a8">No valid Contact Compliance rows found. Paste the HTML table, not a screenshot.</p>';
            } else {
                $matched = 0;
                $unknown = [];
                foreach ($entries as $id => $metrics) {
                    $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $id]);
                    if (!$user || !in_array('ROLE_DRIVER', $user->getRoles(), true)) { $unknown[] = $id; continue; }
                    $matched++;
                }
                $ignored = array_values(array_intersect($unknown, array_filter(array_map('strtoupper', (array)$request->request->all('ignore_ids')))));
                $db->beginTransaction();
                try {
                    $reportPosition = 0;
                    foreach ($entries as $id => [$addresses, $contacts, $rate]) {
                        ++$reportPosition;
                        if (in_array($id, $unknown, true)) continue;
                        $db->executeStatement('DELETE FROM driver_cc_unmatched WHERE week_start = ? AND transporter_id = ?', [$weekStart, $id]);
                        $db->executeStatement('INSERT INTO driver_contact_compliance (week_start, transporter_id, total_addresses, total_contacts, compliance_percent, report_position) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE total_addresses=VALUES(total_addresses), total_contacts=VALUES(total_contacts), compliance_percent=VALUES(compliance_percent), report_position=VALUES(report_position)', [$weekStart, $id, $addresses, $contacts, $rate, $reportPosition]);
                    }
                    foreach ($unknown as $id) {
                        $db->executeStatement('INSERT INTO driver_cc_unmatched (week_start, transporter_id, ignored) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE ignored=VALUES(ignored)', [$weekStart, $id, in_array($id, $ignored, true) ? 1 : 0]);
                    }
                    $db->commit();
                } catch (\Throwable $error) { $db->rollBack(); throw $error; }
                $notice = '<p class="perf-style-3f8a2ca06b">Saved '.$matched.' matched driver results.</p>';
                if ($unknown) $notice .= '<p class="perf-style-2e86f203a8">'.count($unknown).' Transporter IDs are not linked to drivers. Create these driver accounts and import again, or ignore each ID below.</p>';

            }
        }
        if ($request->query->has('saved')) $notice = '<p class="perf-style-3f8a2ca06b">Contact Compliance report saved for '.(int)$request->query->get('saved').' drivers.</p>';
        $escape = static fn(?string $v): string => htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows = '';
        $count = 0;
        foreach ($em->getRepository(User::class)->findBy([], ['displayName' => 'ASC']) as $user) {
            if (!in_array('ROLE_DRIVER', $user->getRoles(), true)) continue;
            ++$count;
            $rows .= '<tr><td>'.$count.'</td><td>'.$escape($user->getDisplayName()).'</td>'
                .($full ? '<td>'.$escape($user->getTransporterId() ?: 'Not assigned').'</td>' : '')
                .'</tr>';
        }
        $ccRows = '';
        $ccCount = 0;
        $report = $db->fetchAllAssociative('SELECT transporter_id, total_addresses, total_contacts, compliance_percent FROM driver_contact_compliance WHERE week_start = ? ORDER BY report_position ASC, transporter_id ASC', [$weekStart]);
        $ccById = [];
        foreach ($report as $ccRecord) $ccById[strtoupper(trim((string)$ccRecord['transporter_id']))] = $ccRecord;
        foreach ($report as $r) {
            $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $r['transporter_id']]);
            if (!$user) continue;
            $ccCount++;
            $ccRows .= '<tr><td>'.$ccCount.'</td><td>'.$escape($user->getDisplayName()).'</td>'.($full ? '<td>'.$escape($r['transporter_id']).'</td>' : '').'<td>'.(int)$r['total_addresses'].'</td><td>'.(int)$r['total_contacts'].'</td><td>'.number_format((float)$r['compliance_percent'], 2).'%</td></tr>';
        }
        $unmatchedRows = $db->fetchAllAssociative('SELECT transporter_id, ignored FROM driver_cc_unmatched WHERE week_start = ? ORDER BY transporter_id', [$weekStart]);
        // Old unmatched records can survive after a driver is created or matched in a later import.
        $unmatchedRows = array_values(array_filter($unmatchedRows, static fn(array $row): bool => !isset($ccById[strtoupper(trim((string)$row['transporter_id']))])));
        $unmatchedPanel = '';
        if ($full && $unmatchedRows) {
            $ignoredCount = count(array_filter($unmatchedRows, static fn(array $row): bool => (bool) $row['ignored']));
            $allIgnored = $ignoredCount === count($unmatchedRows);
            $unmatchedPanel = $allIgnored ? '<details class="perf-style-f4e719ae06"><summary class="perf-style-2048b8577e">'.count($unmatchedRows).' IDs ignored · View ignored IDs</summary>' : '';
            $unmatchedPanel .= '<div class="perf-style-24e1d99b41"><h3 class="perf-style-ad7f18b19e">Unmatched Transporter IDs</h3><p>Create missing drivers in <a href="/admin/users?view=drivers">Driver Management</a> and import the report again, or ignore individual IDs.</p><form method="post" action="/driver-performance?tab=cc&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('cc_import')->getValue()).'"><input type="hidden" name="cc_action" value="ignore"><table><thead><tr><th>Transporter ID</th><th>Status</th><th>Ignore</th><th>Delete</th></tr></thead><tbody>';
            foreach ($unmatchedRows as $r) {
                $id = $escape($r['transporter_id']);
                $existingDriver = $em->getRepository(User::class)->findOneBy(['transporterId' => $r['transporter_id']]);
                $status = $r['ignored'] ? 'Ignored' : (($existingDriver && in_array('ROLE_DRIVER', $existingDriver->getRoles(), true)) ? 'Driver exists — reimport CC report' : 'Needs driver account');
                $unmatchedPanel .= '<tr><td>'.$id.'</td><td>'.$status.'</td><td><input type="checkbox" name="ignore_ids[]" value="'.$id.'"'.($r['ignored'] ? ' checked' : '').'></td><td><input type="checkbox" name="delete_ids[]" value="'.$id.'"></td></tr>';
            }
            $unmatchedPanel .= '</tbody></table><button type="submit">Save ignore choices</button> <button type="submit" name="cc_action" value="delete" data-perf-confirm-unmatched="1" class="perf-style-353f94387d">Delete selected</button></form></div>'.($allIgnored ? '</details>' : '');
        }
        $ccPanel = '<section class="card" id="cc-panel"'.($tab !== 'cc' ? ' hidden' : '').'><h2>CC (Contact Compliance)</h2><p class="muted">Week beginning '.$escape($weekStart).' · '.$ccCount.' drivers imported'.($full && !empty($ignoredCount) ? ' · '.$ignoredCount.' IDs ignored' : '').'</p>'.$notice.$unmatchedPanel;
        if ($full) $ccPanel .= '<details class="dsp-import"><summary>Import / Replace CC Report <span class="perf-style-88159e98b2">PDF · Excel · HTML · CSV</span></summary><form method="post" enctype="multipart/form-data" action="/driver-performance?tab=cc&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('cc_import')->getValue()).'"><div class="dsp-import-grid"><div><label class="dsp-dropzone" id="cc-drop" for="cc-file"><span class="dsp-upload-icon">⇧</span><strong>Choose a report or drag it here</strong><span class="dsp-muted">PDF, XLSX, MHTML, HTML, CSV or TXT · Max 20 MB</span><span class="dsp-file-label" id="cc-file-name">No file selected</span></label><input id="cc-file" type="file" name="cc_file" accept=".pdf,.xlsx,.mhtml,.mht,.html,.htm,.csv,.txt" class="dsp-file-input"></div><div><label class="dsp-paste-label" for="cc-paste">Or paste report data</label><textarea id="cc-paste" name="cc_html" rows="6" maxlength="2000000" placeholder="Paste CC Report HTML, CSV or tab-separated rows here"></textarea><p class="dsp-muted">Use a file or pasted data — only one is needed.</p></div></div><div class="dsp-import-actions"><span class="dsp-muted">Import replaces the selected week’s CC Report records.</span><button class="dsp-import-button" type="submit">Import CC Report</button></div></form></details><script src="/driver-performance-inline-eaf30b1d630e.js"></script>';
        $ccPanel .= $ccCount ? '<div class="perf-style-a632715a5f"><table><thead><tr><th>#</th><th>Driver name</th>'.($full ? '<th>Transporter ID</th>' : '').'<th>Total addresses</th><th>Total contacts</th><th>Contact compliance</th></tr></thead><tbody>'.$ccRows.'</tbody></table></div>' : '<p>No Contact Compliance report imported for this week.</p>';
        $ccPanel .= '</section>';
        $podResults = $db->fetchAllAssociative('SELECT * FROM driver_pod_report WHERE week_start = ? ORDER BY report_position, transporter_id', [$weekStart]);
        $podById = [];
        foreach ($podResults as $podRecord) $podById[strtoupper(trim((string)$podRecord['transporter_id']))] = $podRecord;
        $podRows = '';
        $podCount = 0;
        foreach ($podResults as $r) {
            $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $r['transporter_id']]);
            if (!$user) continue;
            $podRows .= '<tr><td>'.(++$podCount).'</td><td>'.$escape($user->getDisplayName()).'</td>'.($full ? '<td>'.$escape($r['transporter_id']).'</td>' : '');
            foreach (['opportunities','success','bypass','rejects','blurry_photo','no_package','package_in_car','package_too_close','photo_too_dark'] as $metric) $podRows .= '<td>'.(int)$r[$metric].'</td>';
            $podRows .= '</tr>';
        }
        $unmatchedStatus = static function (array $record) use ($em): string {
            if ($record['ignored']) return 'Ignored';
            $driver = $em->getRepository(User::class)->findOneBy(['transporterId' => $record['transporter_id']]);
            return $driver && in_array('ROLE_DRIVER', $driver->getRoles(), true) ? 'Driver exists — reimport report' : 'Needs driver account';
        };
        $podUnmatched = $full ? $db->fetchAllAssociative('SELECT u.transporter_id, u.ignored FROM driver_pod_unmatched u WHERE u.week_start = ? AND NOT EXISTS (SELECT 1 FROM driver_pod_report r WHERE r.week_start = u.week_start AND r.transporter_id = u.transporter_id) ORDER BY u.transporter_id', [$weekStart]) : [];
        $podIgnored = count(array_filter($podUnmatched, static fn($r) => (bool)$r['ignored']));
        $podPanel = '<section class="card"'.($tab !== 'pod' ? ' hidden' : '').'><h2>POD (Photo on Delivery)</h2><p class="muted">Week beginning '.$escape($weekStart).' · '.$podCount.' drivers imported'.($full && $podIgnored ? ' · '.$podIgnored.' IDs ignored' : '').'</p>'.($tab === 'pod' ? $notice : '');
        if ($full && $podUnmatched) {
            $allIgnored = $podIgnored === count($podUnmatched);
            $podPanel .= $allIgnored ? '<details><summary>'.count($podUnmatched).' IDs ignored · View ignored IDs</summary>' : '';
            $podPanel .= '<div class="perf-style-d4c443c814"><strong>Unmatched Transporter IDs</strong><p>Create missing accounts in <a href="/admin/users?view=drivers">Driver Management</a> and reimport, or ignore IDs.</p><form method="post" action="/driver-performance?tab=pod&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('pod_import')->getValue()).'"><input type="hidden" name="pod_action" value="ignore"><table><tr><th>Transporter ID</th><th>Status</th><th>Ignore</th><th>Delete</th></tr>';
            foreach ($podUnmatched as $r) $podPanel .= '<tr><td>'.$escape($r['transporter_id']).'</td><td>'.$escape($unmatchedStatus($r)).'</td><td><input type="checkbox" name="ignore_ids[]" value="'.$escape($r['transporter_id']).'"'.($r['ignored'] ? ' checked' : '').'></td><td><input type="checkbox" name="delete_ids[]" value="'.$escape($r['transporter_id']).'"></td></tr>';
            $podPanel .= '</table><button type="submit">Save ignore choices</button> <button type="submit" name="pod_action" value="delete" data-perf-confirm-unmatched="1" class="perf-style-353f94387d">Delete selected</button></form></div>'.($allIgnored ? '</details>' : '');
        }
        if ($full) $podPanel .= '<details class="dsp-import"><summary>Import / Replace POD Report <span class="perf-style-88159e98b2">PDF · Excel · HTML · CSV</span></summary><form method="post" enctype="multipart/form-data" action="/driver-performance?tab=pod&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('pod_import')->getValue()).'"><div class="dsp-import-grid"><div><label class="dsp-dropzone" id="pod-drop" for="pod-file"><span class="dsp-upload-icon">⇧</span><strong>Choose a report or drag it here</strong><span class="dsp-muted">PDF, XLSX, MHTML, HTML, CSV or TXT · Max 20 MB</span><span class="dsp-file-label" id="pod-file-name">No file selected</span></label><input id="pod-file" type="file" name="pod_file" accept=".pdf,.xlsx,.mhtml,.mht,.html,.htm,.csv,.txt" class="dsp-file-input"></div><div><label class="dsp-paste-label" for="pod-paste">Or paste report data</label><textarea id="pod-paste" name="pod_paste" rows="6" maxlength="2000000" placeholder="Paste POD Report HTML, CSV or tab-separated rows here"></textarea><p class="dsp-muted">Use a file or pasted data — only one is needed.</p></div></div><div class="dsp-import-actions"><span class="dsp-muted">Import replaces the selected week’s POD Report records.</span><button class="dsp-import-button" type="submit">Import POD Report</button></div></form></details><script src="/driver-performance-inline-eaf30b1d630e.js"></script>';
        $podPanel .= $podCount ? '<div class="perf-style-a632715a5f"><table><thead><tr><th>#</th><th>Driver name</th>'.($full ? '<th>Transporter ID</th>' : '').'<th>Opportunities</th><th>Success</th><th>Bypass</th><th>Rejects</th><th>Blurry photo</th><th>No package</th><th>Package in car</th><th>Package too close</th><th>Photo too dark</th></tr></thead><tbody>'.$podRows.'</tbody></table></div>' : '<p>No POD report imported for this week.</p>';
        $podPanel .= '</section>';
        $dwcResults = $db->fetchAllAssociative('SELECT * FROM driver_dwc_iadc WHERE week_start = ? ORDER BY report_position, transporter_id', [$weekStart]);
        $dwcRows = ''; $dwcCount = 0;
        foreach ($dwcResults as $r) {
            $driver = $em->getRepository(User::class)->findOneBy(['transporterId' => $r['transporter_id']]);
            if (!$driver) continue;
            $dwcRows .= '<tr><td>'.(++$dwcCount).'</td><td><strong>'.$escape($driver->getDisplayName()).'</strong>'.($full ? '<div class="perf-style-075db4d706">'.$escape($r['transporter_id']).'</div>' : '').'</td><td>'.number_format((float)$r['dwc_percent'], 2).'%</td><td>'.number_format((float)$r['iadc_percent'], 2).'%</td></tr>';
        }
        $dwcUnmatched = $full ? $db->fetchAllAssociative('SELECT u.transporter_id, u.ignored FROM driver_dwc_unmatched u WHERE u.week_start = ? AND NOT EXISTS (SELECT 1 FROM driver_dwc_iadc r WHERE r.week_start = u.week_start AND r.transporter_id = u.transporter_id) ORDER BY u.transporter_id', [$weekStart]) : [];
        $dwcIgnored = count(array_filter($dwcUnmatched, static fn($r) => (bool)$r['ignored']));
        $dwcPanel = '<section class="card"'.($tab !== 'dwc' ? ' hidden' : '').'><h2>DWC/IADC Report</h2><p class="muted">Week beginning '.$escape($weekStart).' · '.$dwcCount.' drivers imported'.($full && $dwcIgnored ? ' · '.$dwcIgnored.' IDs ignored' : '').'</p>'.($tab === 'dwc' ? $notice : '');
        if ($full && $dwcUnmatched) {
            $allIgnored = $dwcIgnored === count($dwcUnmatched);
            $dwcPanel .= $allIgnored ? '<details><summary>'.count($dwcUnmatched).' IDs ignored · View ignored IDs</summary>' : '';
            $dwcPanel .= '<div class="perf-style-d4c443c814"><strong>Unmatched Transporter IDs</strong><p>Create missing drivers in <a href="/admin/users?view=drivers">Driver Management</a> and reimport, or ignore individual IDs.</p><form method="post" action="/driver-performance?tab=dwc&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('dwc_import')->getValue()).'"><input type="hidden" name="dwc_action" value="ignore"><table><tr><th>Transporter ID</th><th>Status</th><th>Ignore</th><th>Delete</th></tr>';
            foreach ($dwcUnmatched as $r) $dwcPanel .= '<tr><td>'.$escape($r['transporter_id']).'</td><td>'.$escape($unmatchedStatus($r)).'</td><td><input type="checkbox" name="ignore_ids[]" value="'.$escape($r['transporter_id']).'"'.($r['ignored'] ? ' checked' : '').'></td><td><input type="checkbox" name="delete_ids[]" value="'.$escape($r['transporter_id']).'"></td></tr>';
            $dwcPanel .= '</table><button type="submit">Save ignore choices</button> <button type="submit" name="dwc_action" value="delete" data-perf-confirm-unmatched="1" class="perf-style-353f94387d">Delete selected</button></form></div>'.($allIgnored ? '</details>' : '');
        }
        if ($full) $dwcPanel .= '<details class="dsp-import"><summary>Import / Replace DWC/IADC Report <span class="perf-style-88159e98b2">PDF · Excel · HTML · CSV</span></summary><form method="post" enctype="multipart/form-data" action="/driver-performance?tab=dwc&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('dwc_import')->getValue()).'"><div class="dsp-import-grid"><div><label class="dsp-dropzone" id="dwc-drop" for="dwc-file"><span class="dsp-upload-icon">⇧</span><strong>Choose a report or drag it here</strong><span class="dsp-muted">PDF, XLSX, MHTML, HTML, CSV or TXT · Max 20 MB</span><span class="dsp-file-label" id="dwc-file-name">No file selected</span></label><input id="dwc-file" type="file" name="dwc_file" accept=".pdf,.xlsx,.mhtml,.mht,.html,.htm,.csv,.txt" class="dsp-file-input"></div><div><label class="dsp-paste-label" for="dwc-paste">Or paste report data</label><textarea id="dwc-paste" name="dwc_paste" rows="6" maxlength="2000000" placeholder="Paste DWC/IADC Report HTML, CSV or tab-separated rows here"></textarea><p class="dsp-muted">Use a file or pasted data — only one is needed.</p></div></div><div class="dsp-import-actions"><span class="dsp-muted">Import replaces the selected week’s DWC/IADC Report records.</span><button class="dsp-import-button" type="submit">Import DWC/IADC Report</button></div></form></details><script src="/driver-performance-inline-eaf30b1d630e.js"></script>';
        $dwcPanel .= $dwcCount ? '<div class="perf-style-a632715a5f"><table><thead><tr><th>#</th><th>Driver name</th><th>DWC %</th><th>IADC %</th></tr></thead><tbody>'.$dwcRows.'</tbody></table></div>' : '<p>No DWC/IADC report imported for this week.</p>';
        $dwcPanel .= '</section>';
        $dspResults = $db->fetchAllAssociative('SELECT * FROM driver_dsp_summary WHERE week_start = ? ORDER BY report_position, transporter_id', [$weekStart]);
        $dspRows = ''; $weeklyRows = ''; $dspCount = 0;
        foreach ($dspResults as $r) {
            $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $r['transporter_id']]);
            if (!$user) continue;
            $dspRows .= '<tr><td>'.(++$dspCount).'</td><td><strong>'.$escape($user->getDisplayName()).'</strong>'.($full ? '<div class="perf-style-cbd6cb7286">'.$escape($r['transporter_id']).'</div>' : '').'</td>';
            foreach (['delivered','dcr','dsc_dpmo','lor_dpmo','pod','cc','ce','cdf_dpmo','psb'] as $metric) {
                $value = $r[$metric] === null ? '-' : $r[$metric];
                if (in_array($metric, ['dcr','pod','cc'], true) && $value !== '-') $value = number_format((float)$value, 2).'%';
                $dspRows .= '<td>'.$escape((string)$value).'</td>';
            }
            $dspRows .= '</tr>';
        }
        $netradyneRows = $db->fetchAllAssociative('SELECT * FROM driver_netradyne_weekly WHERE week_start = ?', [$weekStart]);
        $netradyneById = array_column($netradyneRows, null, 'transporter_id');
        $dwcById = array_column($dwcResults, null, 'transporter_id');
        $weeklyFico = [];
        $weekEndExclusive = (new \DateTimeImmutable($weekStart))->modify('+7 days')->format('Y-m-d');
        foreach ($db->fetchAllAssociative('SELECT driver_id, shift_date, fisco_score FROM driver_daily_check WHERE shift_date >= ? AND shift_date < ? AND fisco_score > 0 ORDER BY shift_date DESC', [$weekStart, $weekEndExclusive]) as $check) {
            $driverId = (int)$check['driver_id'];
            if (!array_key_exists($driverId, $weeklyFico)) $weeklyFico[$driverId] = $check;
        }
        // Explicit weekly Ementor scores take priority over daily-check values.
        foreach ($db->fetchAllAssociative('SELECT e.transporter_id, e.ementor_score, u.id AS driver_id FROM driver_ementor_weekly e INNER JOIN app_user u ON u.transporter_id = e.transporter_id WHERE e.week_start = ?', [$weekStart]) as $score) {
            $weeklyFico[(int)$score['driver_id']] = ['fisco_score' => $score['ementor_score']];
        }
        // The latest calendar day with a positive effective FICO score wins.
        // On each day the later driver submission or manager correction takes priority.
        $latestDailyFico = [];
        $ficoSql = 'SELECT scores.driver_id,scores.score_date,scores.effective_score FROM (
            SELECT c.driver_id,c.shift_date AS score_date,
                CASE WHEN o.fisco_score IS NULL OR (c.post_updated_at IS NOT NULL AND c.post_updated_at > o.updated_at)
                     THEN c.fisco_score ELSE o.fisco_score END AS effective_score
            FROM driver_daily_check c
            LEFT JOIN operations_daily_score_override o ON o.driver_id=c.driver_id AND o.report_date=c.shift_date
            WHERE c.shift_date >= ? AND c.shift_date < ? AND c.fisco_score IS NOT NULL
            UNION ALL
            SELECT o.driver_id,o.report_date AS score_date,o.fisco_score AS effective_score
            FROM operations_daily_score_override o
            LEFT JOIN driver_daily_check c ON c.driver_id=o.driver_id AND c.shift_date=o.report_date AND c.fisco_score IS NOT NULL
            WHERE o.report_date >= ? AND o.report_date < ? AND o.fisco_score IS NOT NULL AND c.driver_id IS NULL
        ) scores WHERE scores.effective_score > 0 ORDER BY scores.score_date DESC';
        foreach ($db->fetchAllAssociative($ficoSql, [$weekStart,$weekEndExclusive,$weekStart,$weekEndExclusive]) as $check) {
            $id=(int)$check['driver_id'];
            if (!isset($latestDailyFico[$id])) $latestDailyFico[$id]=['fisco_score'=>$check['effective_score']];
        }
        foreach ($latestDailyFico as $id=>$score) $weeklyFico[$id]=$score;
        $cdfPointsFor = static function (float $v) use ($cdfBands,$cdfBelow,$cdfBelowPoints): float {
            foreach ($cdfBands as [$min,$points]) if ($v >= (float)$min) return (float)$points;
            return $v < $cdfBelow ? $cdfBelowPoints : 0.0;
        };
        $cdfShadeFor = static function (float $points) use ($cdfColors): string {
            return $points >= 5 ? $cdfColors['green_color'] : ($points <= 0 ? $cdfColors['red_color'] : $cdfColors['amber_color']);
        };
        $rowShade = static function (string $metric, float $value) use ($colourBands, $savedRowColourOverrides): ?string {
            foreach ($colourBands[$metric] ?? [] as [$threshold, $points]) {
                $match = in_array($metric, ['psb','ce'], true) ? $value <= (float)$threshold : ($metric === 'cc' && (float)$threshold == 99.8 ? $value > (float)$threshold : $value >= (float)$threshold);
                if ($match) return $savedRowColourOverrides[$metric][(string)(float)$threshold] ?? null;
            }
            return null;
        };
        $scoreAtLeast = static function (float $value, array $bands, bool $strictCc = false): float {
            foreach ($bands as [$minimum, $points]) {
                if ($strictCc && (float)$minimum == 99.8 ? $value > (float)$minimum : $value >= (float)$minimum) return (float)$points;
            }
            return 0.0;
        };
        $scoreAtMost = static function (float $value, array $bands, float $fallback = 0.0): float {
            foreach ($bands as [$maximum, $points]) if ($value <= (float)$maximum) return (float)$points;
            return $fallback;
        };
        // Every violation incurs its configured percentage-point deduction.
        $netradynePenalty = static function (?array $values) use ($netradyneMultipliers): float {
            $deduction = 0.0;
            foreach ($netradyneMultipliers as $key => $multiplier) {
                $count = max(0, (int)($values[$key] ?? 0));
                $deduction -= $count * $multiplier;
            }
            return round($deduction, 2);
        };
        $rankedRows = [];
        foreach ($dspResults as $record) {
            $driver = $em->getRepository(User::class)->findOneBy(['transporterId' => $record['transporter_id']]);
            if (!$driver) continue;
            $driverDwc = $dwcById[$record['transporter_id']] ?? null;
            $ficoRaw = $weeklyFico[$driver->getId()]['fisco_score'] ?? null;
            $scores = [
                'fico' => $ficoRaw === null ? null : $scoreAtLeast((float)$ficoRaw, $ficoBands),
                'dwc' => $driverDwc === null ? null : $scoreAtLeast((float)$driverDwc['dwc_percent'], $dwcBands),
                'psb' => $record['psb'] === null ? null : $scoreAtMost((float)$record['psb'], $psbBands),
                'dsc_dpmo' => $record['dsc_dpmo'] === null ? null : (static function (float $value) use ($dpmoBands): float { foreach ($dpmoBands as [$minimum, $points]) if ($value >= (float)$minimum) return (float)$points; return (float)end($dpmoBands)[1]; })((float)$record['dsc_dpmo']),
                'pod' => $record['pod'] === null ? null : $scoreAtLeast((float)$record['pod'], $podBands),
                'cc' => $record['cc'] === null ? null : $scoreAtLeast((float)$record['cc'], $ccBands, true),
                'ce' => $record['ce'] === null ? null : $scoreAtMost((float)$record['ce'], $ceBands, (float)$ceAbovePoints),
                'cdf_dpmo' => $record['cdf_dpmo'] === null ? null : $cdfPointsFor((float)$record['cdf_dpmo']),
            ];
            $complete = !in_array(null, $scores, true);
            $rankedRows[$record['transporter_id']] = ['complete' => $complete, 'total' => max(0, round(array_sum($scores) + $netradynePenalty($netradyneById[$record['transporter_id']] ?? null), 2))];
        }
        if ($request->isMethod('POST') && $request->request->get('coaching_action') === 'save') {
            if (!$full || !$csrfManager->isTokenValid(new \Symfony\Component\Security\Csrf\CsrfToken('coaching_week_'.$weekStart, (string)$request->request->get('_token')))) throw $this->createAccessDeniedException();
            $coachingId = trim((string)$request->request->get('transporter_id'));
            if (!isset($rankedRows[$coachingId])) throw new InvalidArgumentException('Unknown driver for this week.');
            $done = $request->request->get('done') === '1' ? 1 : 0;
            $db->executeStatement('INSERT INTO driver_performance_coaching (week_start,transporter_id,done,updated_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE done=VALUES(done),updated_at=NOW()',[$weekStart,$coachingId,$done]);
            return new \Symfony\Component\HttpFoundation\JsonResponse(['ok'=>true,'done'=>(bool)$done]);
        }
        $coachingDone = array_fill_keys($db->fetchFirstColumn('SELECT transporter_id FROM driver_performance_coaching WHERE week_start=? AND done=1',[$weekStart]),true);
        $ranking = [];
        foreach ($rankedRows as $id => $entry) if ($entry['total'] !== null) $ranking[] = ['id'=>$id, 'total'=>$entry['total']];
        usort($ranking, static fn(array $a,array $b): int => ($b['total'] <=> $a['total']) ?: strcmp($a['id'], $b['id']));
        if ($request->query->has('bonus_export')) {
            if (!$full) throw $this->createAccessDeniedException();
            $thresholdRaw=(string)$request->query->get('bonus_export');
            if (!is_numeric($thresholdRaw) || (float)$thresholdRaw<0 || (float)$thresholdRaw>100) throw new InvalidArgumentException('Minimum percentage must be between 0 and 100.');
            $threshold=(float)$thresholdRaw;
            $weekEnd=(new \DateTimeImmutable($weekStart))->modify('+6 days')->format('d M Y');
            $weekStartDisplay=(new \DateTimeImmutable($weekStart))->format('d M Y');
            $weekNumber=(new \DateTimeImmutable($weekStart))->format('W');
            $report='<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Driver bonus report - Week '.$weekNumber.'</title><style>@page{size:A4 landscape;margin:12mm}body{font:12px Arial,sans-serif;color:#17314e}h1{margin:0 0 7px}p{margin:4px 0 15px}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #b9c8d8;padding:9px 7px;text-align:left;overflow-wrap:anywhere}th{background:#e9f1f8}tr{break-inside:avoid}th:nth-child(1){width:5%}th:nth-child(2){width:22%}th:nth-child(3){width:23%}th:nth-child(4){width:10%}th:nth-child(5){width:13%}th:nth-child(6){width:17%}th:nth-child(7){width:10%}.toolbar{margin:14px 0;display:flex;gap:12px}.report-action{background:#17456c;color:#fff;border:0;border-radius:9px;padding:13px 20px;font-weight:700;font-size:14px;cursor:pointer}.report-action:hover{background:#24638e}@media print{.toolbar{display:none}}</style></head><body><h1>Driver Bonus Review — Week '.$weekNumber.'</h1><p>'.$escape($weekStartDisplay).' to '.$escape($weekEnd).' · Minimum score: '.number_format($threshold,2).'% · Ranked highest first</p><div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button> <button onclick="window.close()">Close</button></div><table><thead><tr><th>Rank</th><th>Driver</th><th>Transporter ID</th><th>Score</th><th>Data</th><th>Comments</th><th>Bonus £</th></tr></thead><tbody>';
            $shown=0;
            foreach ($ranking as $rankIndex=>$leader) {
                if ($leader['total']<$threshold) continue;
                $bonusUser=$em->getRepository(User::class)->findOneBy(['transporterId'=>$leader['id']]);
                if (!$bonusUser) continue;
                ++$shown;
                $report.='<tr><td>'.($rankIndex+1).'</td><td>'.$escape($bonusUser->getDisplayName()).'</td><td>'.$escape($leader['id']).'</td><td>'.number_format((float)$leader['total'],2).'%</td><td>'.($rankedRows[$leader['id']]['complete']?'Complete':'Provisional').'</td><td></td><td></td></tr>';
            }
            if (!$shown) $report.='<tr><td colspan="7">No drivers meet this threshold.</td></tr>';
            $report.='</tbody></table><p>Provisional = one or more performance categories have missing data. Missing scores contribute 0 points; scores are not rescaled.</p></body></html>';
            return new Response($report);
        }
        // Manager-controlled Top 5 is separate from the scoring/ranking algorithm.
        $topFiveAvailable = $db->createSchemaManager()->tablesExist(['driver_performance_top_five']);
        $topFiveSaved = $topFiveAvailable ? $db->fetchAssociative('SELECT min_delivered, selected_ids_json FROM driver_performance_top_five WHERE week_start = ?', [$weekStart]) : false;
        $topFiveThreshold = (int)($topFiveSaved['min_delivered'] ?? 0);
        $topFiveSelected = $topFiveSaved ? json_decode((string)$topFiveSaved['selected_ids_json'], true) : null;
        if (!is_array($topFiveSelected)) $topFiveSelected = null;
        $topFiveDelivered = [];
        foreach ($dspResults as $record) $topFiveDelivered[(string)$record['transporter_id']] = $record['delivered'] === null ? null : (int)$record['delivered'];
        if ($request->isMethod('POST') && $tab === 'weekly' && in_array($request->request->get('top_five_action'), ['threshold','selection'], true)) {
            if (!$full || !$this->isCsrfTokenValid('top_five_week_'.$weekStart, (string)$request->request->get('_token'))) throw $this->createAccessDeniedException();
            if (!$topFiveAvailable) throw new \RuntimeException('Top 5 database migration is required.');
            $action = (string)$request->request->get('top_five_action');
            if ($action === 'threshold') {
                $raw = (string)$request->request->get('min_delivered', '');
                if (!preg_match('/^\d{1,7}$/', $raw)) throw new InvalidArgumentException('Minimum delivered must be a nonnegative whole number.');
                $topFiveThreshold = (int)$raw;
                if ($topFiveSelected !== null) $topFiveSelected = array_values(array_filter($topFiveSelected, static fn($id) => isset($topFiveDelivered[$id]) && $topFiveDelivered[$id] >= $topFiveThreshold));
            } else {
                $ids = $request->request->all('selected_ids');
                if (count($ids) > 5 || count($ids) !== count(array_unique($ids)) || array_filter($ids, static fn($id) => !is_string($id))) throw new InvalidArgumentException('Select at most five distinct drivers.');
                $rankedIds = array_column($ranking, 'id');
                foreach ($ids as $id) {
                    if (!in_array($id, $rankedIds, true) || !isset($topFiveDelivered[$id]) || $topFiveDelivered[$id] < $topFiveThreshold) throw new InvalidArgumentException('Selected driver does not qualify.');
                }
                $order = json_decode((string)$request->request->get('selected_order', '[]'), true);
                if (!is_array($order) || count($order) !== count($ids) || count(array_unique($order)) !== count($ids) || array_diff($order, $ids) || array_diff($ids, $order)) throw new InvalidArgumentException('Invalid Top 5 selection order.');
                $topFiveSelected = array_values($order);
            }
            $db->executeStatement('INSERT INTO driver_performance_top_five (week_start, min_delivered, selected_ids_json, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE min_delivered = VALUES(min_delivered), selected_ids_json = VALUES(selected_ids_json), updated_at = NOW()', [$weekStart, $topFiveThreshold, json_encode($topFiveSelected)]);
            return $this->redirectToRoute('driver_performance_hub', ['week'=>$weekStart]);
        }
        $topFiveCandidates = '';
        foreach ($ranking as $i => $leader) {
            $id = $leader['id'];
            if (!isset($topFiveDelivered[$id])) continue;
            $leaderUser = $em->getRepository(User::class)->findOneBy(['transporterId'=>$id]);
            if (!$leaderUser) continue;
            $topFiveCandidates .= '<div class="top-five-candidate" data-delivered="'.$topFiveDelivered[$id].'" class="perf-style-2e7f94fbeb"><input type="checkbox" name="selected_ids[]" value="'.$escape($id).'"'.(is_array($topFiveSelected) && in_array($id, $topFiveSelected, true) ? ' checked' : '').'><span class="perf-style-d91da1f681">'.$escape($leaderUser->getDisplayName()).' · Rank '.($i+1).' · '.$escape((string)$leader['total']).'/100</span><strong>'.$topFiveDelivered[$id].' delivered</strong></div>';
        }
        $topFiveRows = '';
        $leadersById = array_column($ranking, null, 'id');
        $displayLeaders = $topFiveSelected === null ? array_slice($ranking, 0, 5) : array_values(array_filter(array_map(static fn($id) => $leadersById[$id] ?? null, $topFiveSelected)));
        foreach ($displayLeaders as $index => $leader) {
            $leaderUser = $em->getRepository(User::class)->findOneBy(['transporterId' => $leader['id']]);
            if (!$leaderUser) continue;
            $rank = $index + 1;
            $topFiveRows .= '<div class="top-five-entry"><span class="top-five-trophy" aria-label="Rank '.$rank.'">🏆<small>'.$rank.'</small></span><span class="top-five-name">'.$escape($leaderUser->getDisplayName()).'</span><span class="top-five-score">'.$escape((string)$leader['total']).' / 100</span><span class="top-five-rank">Rank '.$rank.'</span></div>';
        }
        $topFiveWeek = (new \DateTimeImmutable($weekStart))->format('W');
        $topFiveCelebrationCss = '';
        $topFiveManager = $full ? '<details class="perf-style-944de33872" open><summary class="perf-style-e4ecd9c133">Manager selection</summary><form method="post" action="/driver-performance?week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('top_five_week_'.$weekStart)->getValue()).'"><input type="hidden" name="top_five_action" value="threshold"><label>Minimum delivered parcels <input id="top-five-threshold" type="number" min="0" max="9999999" step="1" name="min_delivered" value="'.$topFiveThreshold.'" required class="perf-style-60746827b0"></label> <button type="submit">Save Threshold</button></form><form method="post" action="/driver-performance?week='.$escape($weekStart).'" id="top-five-selection-form"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('top_five_week_'.$weekStart)->getValue()).'"><input type="hidden" name="top_five_action" value="selection"><p>Choose up to five qualifying drivers. Changes to the threshold must be saved separately.</p><div id="top-five-candidates" class="perf-style-baaaf5b57c">'.$topFiveCandidates.'</div><p id="top-five-count" aria-live="polite"></p><div class="perf-style-bcc3776abb">Recognition order (1–5)</div><p class="perf-style-6cb285c61d">Use ↑ and ↓ to set the recognition positions. Performance ranks and scores do not change.</p><div id="top-five-order"></div><input type="hidden" name="selected_order" id="top-five-order-value" value="'.$escape(json_encode($topFiveSelected ?? [])).'"><button type="submit">Save Top 5 Selection &amp; Order</button></form></details>' : '';
        $topFivePanel = $topFiveCelebrationCss.'<button type="button" id="top-five-open" class="top-five-button">🏆 Top 5 Drivers</button><dialog id="top-five-dialog" class="top-five-dialog"><div class="top-five-heading"><div><strong>🏆 Top 5 Drivers</strong><div class="top-five-week">Week '.$escape($topFiveWeek).' · '.$escape((new \DateTimeImmutable($weekStart))->format('d M Y')).'</div></div><div class="top-five-heading-actions"><img src="/dulogix-logo.png" alt="Dulogix logo" class="top-five-logo"><button type="button" id="top-five-close" aria-label="Close top five drivers">✕</button></div></div>'.$topFiveManager.'<div class="top-five-celebration"><div class="top-five-celebration-title">🎉 Congratulations to our Top 5! 🎉</div><p>Outstanding effort, dedication and delivery excellence this week. You have earned your place among our best performers — keep shining! 🌟</p></div><div class="top-five-list">'.($topFiveRows ?: '<p>No ranked drivers for this week yet.</p>') .'</div><div class="top-five-share"><button type="button" id="top-five-copy">📋 Copy</button><button type="button" id="top-five-whatsapp">💬 Share on WhatsApp</button></div><p id="top-five-feedback" class="top-five-feedback" aria-live="polite"></p></dialog>';
        $positions = [];
        $previousTotal = null;
        $previousRank = 0;
        foreach ($ranking as $i => $entry) {
            if ($previousTotal === null || $entry['total'] !== $previousTotal) $previousRank = $i + 1;
            $positions[$entry['id']] = $previousRank;
            $previousTotal = $entry['total'];
        }
        $priorWeekStart = (new \DateTimeImmutable($weekStart))->modify('-7 days')->format('Y-m-d');
        $priorDsp = $db->fetchAllAssociative('SELECT * FROM driver_dsp_summary WHERE week_start = ?', [$priorWeekStart]);
        $priorDwc = array_column($db->fetchAllAssociative('SELECT * FROM driver_dwc_iadc WHERE week_start = ?', [$priorWeekStart]), null, 'transporter_id');
        $priorNet = array_column($db->fetchAllAssociative('SELECT * FROM driver_netradyne_weekly WHERE week_start = ?', [$priorWeekStart]), null, 'transporter_id');
        $priorFico = [];
        foreach ($db->fetchAllAssociative('SELECT driver_id, fisco_score FROM driver_daily_check WHERE shift_date >= ? AND shift_date < ? AND fisco_score > 0 ORDER BY shift_date DESC', [$priorWeekStart, $weekStart]) as $check) {
            $id = (int)$check['driver_id'];
            if (!isset($priorFico[$id])) $priorFico[$id] = ['fisco_score'=>$check['fisco_score']];
        }
        foreach ($db->fetchAllAssociative('SELECT e.ementor_score, u.id AS driver_id FROM driver_ementor_weekly e INNER JOIN app_user u ON u.transporter_id = e.transporter_id WHERE e.week_start = ?', [$priorWeekStart]) as $check) {
            $priorFico[(int)$check['driver_id']] = ['fisco_score'=>$check['ementor_score']];
        }
        $priorLatest = [];
        foreach ($db->fetchAllAssociative($ficoSql, [$priorWeekStart,$weekStart,$priorWeekStart,$weekStart]) as $check) {
            $id=(int)$check['driver_id'];
            if (!isset($priorLatest[$id])) $priorLatest[$id]=['fisco_score'=>$check['effective_score']];
        }
        foreach ($priorLatest as $id=>$score) $priorFico[$id]=$score;
        $priorRankedRows = [];
        foreach ($priorDsp as $record) {
            $driver = $em->getRepository(User::class)->findOneBy(['transporterId' => $record['transporter_id']]);
            if (!$driver) continue;
            $driverDwc = $priorDwc[$record['transporter_id']] ?? null;
            $ficoRaw = $priorFico[$driver->getId()]['fisco_score'] ?? null;
            $scores = [
                'fico' => $ficoRaw === null ? null : $scoreAtLeast((float)$ficoRaw, $ficoBands),
                'dwc' => $driverDwc === null ? null : $scoreAtLeast((float)$driverDwc['dwc_percent'], $dwcBands),
                'psb' => $record['psb'] === null ? null : $scoreAtMost((float)$record['psb'], $psbBands),
                'dsc_dpmo' => $record['dsc_dpmo'] === null ? null : (static function (float $value) use ($dpmoBands): float { foreach ($dpmoBands as [$minimum, $points]) if ($value >= (float)$minimum) return (float)$points; return (float)end($dpmoBands)[1]; })((float)$record['dsc_dpmo']),
                'pod' => $record['pod'] === null ? null : $scoreAtLeast((float)$record['pod'], $podBands),
                'cc' => $record['cc'] === null ? null : $scoreAtLeast((float)$record['cc'], $ccBands, true),
                'ce' => $record['ce'] === null ? null : $scoreAtMost((float)$record['ce'], $ceBands, (float)$ceAbovePoints),
                'cdf_dpmo' => $record['cdf_dpmo'] === null ? null : $cdfPointsFor((float)$record['cdf_dpmo']),
            ];
            $complete = !in_array(null, $scores, true);
            $priorRankedRows[$record['transporter_id']] = ['complete' => $complete, 'total' => max(0, round(array_sum($scores) + $netradynePenalty($priorNet[$record['transporter_id']] ?? null), 2))];
        }
        $priorRanking = [];
        foreach ($priorRankedRows as $id => $entry) if ($entry['total'] !== null) $priorRanking[] = ['id'=>$id,'total'=>$entry['total']];
        usort($priorRanking, static fn(array $a,array $b): int => ($b['total'] <=> $a['total']) ?: strcmp($a['id'], $b['id']));
        $priorPositions = []; $priorTotal = null; $priorPosition = 0;
        foreach ($priorRanking as $i => $entry) {
            if ($priorTotal === null || $entry['total'] !== $priorTotal) $priorPosition = $i + 1;
            $priorPositions[$entry['id']] = $priorPosition;
            $priorTotal = $entry['total'];
        }
        usort($dspResults, static function(array $a,array $b) use ($rankedRows): int {
            $left = $rankedRows[$a['transporter_id']] ?? ['complete'=>false,'total'=>null];
            $right = $rankedRows[$b['transporter_id']] ?? ['complete'=>false,'total'=>null];
            return ($right['complete'] <=> $left['complete'])
                ?: (($right['total'] ?? -1) <=> ($left['total'] ?? -1))
                ?: strcmp($a['transporter_id'], $b['transporter_id']);
        });
        $statusForScore = static function (float $score): array {
            if ($score >= 93.5) return ['Fantastic_Plus', '#BFE8D0', '#14532D'];
            if ($score >= 85) return ['Fantastic', '#DDF2E7', '#246B4B'];
            if ($score >= 70) return ['Great', '#DCEBFA', '#245B91'];
            if ($score >= 40) return ['Fair', '#FFF0BF', '#8A5A12'];
            return ['Poor', '#FAD9DF', '#9F2338'];
        };
        $ccCounts = [];
        foreach ($db->fetchAllAssociative('SELECT transporter_id, total_addresses, total_contacts FROM driver_contact_compliance WHERE week_start = ?', [$weekStart]) as $ccRecord) $ccCounts[(string)$ccRecord['transporter_id']] = $ccRecord;
        $weeklyRows = '';
        foreach ($dspResults as $r) {
            $user = $em->getRepository(User::class)->findOneBy(['transporterId' => $r['transporter_id']]);
            if (!$user) continue;
            $cell = static function ($value, $suffix = ''): string {
                if ($value === null || $value === '') return '—';
                $display = (string)$value;
                if (is_numeric($value) && preg_match('/^-?\d+\.\d+$/', $display)) {
                    $display = rtrim(rtrim($display, '0'), '.');
                    if ($display === '-0') $display = '0';
                }
                return htmlspecialchars($display, ENT_QUOTES, 'UTF-8').$suffix;
            };
            $net = $netradyneById[$r['transporter_id']] ?? null;
            $dwc = $dwcById[$r['transporter_id']] ?? null;
            $ccReport = $ccById[strtoupper(trim((string)$r['transporter_id']))] ?? null;
            $ccDetails = ($r['cc'] === null ? '' : '<div class="perf-style-308281ce8e">'.$escape((string)$scoreAtLeast((float)$r['cc'], $ccBands, true)).'%</div>').'<details class="metric-detail"><summary>';
            $ccDetails .= $r['cc'] === null ? '—' : '<span data-perf-style="display:inline-block;padding:5px 8px;border-radius:5px;'.($rowShade('cc', (float)$r['cc']) !== null ? 'background:'.$escape((string)$rowShade('cc', (float)$r['cc'])).';color:#17314e' : ((float)$r['cc'] < $ccColors['red_below'] ? 'background:'.$escape((string)$ccColors['red_color']).';color:#9c0006' : ((float)$r['cc'] > $ccColors['green_above'] ? 'background:'.$escape((string)$ccColors['green_color']).';color:#006100' : 'background:'.$escape((string)$ccColors['amber_color']).';color:#9c5700'))).'">'.$cell($r['cc'], '%').'</span>';
            $ccDetails .= ' ▾</summary><div class="metric-detail-panel perf-style-8585d04537"><div class="perf-style-1da918d727"><strong>CC report details</strong><span class="perf-style-10898c41b9">'.$escape($user->getDisplayName()).'</span></div>';
            if ($ccReport) {
                $ccDetails .= '<div class="perf-style-68c83d1680">';
                foreach (['total_addresses'=>'Total addresses','total_contacts'=>'Total contacts','compliance_percent'=>'Contact compliance'] as $key => $label) {
                    $value = $key === 'compliance_percent' ? number_format((float)$ccReport[$key], 2).'%' : (string)(int)$ccReport[$key];
                    $ccDetails .= '<div class="perf-style-893dcf53dc"><div class="perf-style-61e5470ebc">'.$escape($label).'</div><strong>'.$escape($value).'</strong></div>';
                }
                $ccDetails .= '</div>';
            } else {
                $ccDetails .= '<p>No imported CC details for this Transporter ID in the selected week.</p>';
            }
            $ccDetails .= '</div></details>';
            $podReport = $podById[strtoupper(trim((string)$r['transporter_id']))] ?? null;
            $podDetails = ($r['pod'] === null ? '' : '<div class="perf-style-308281ce8e">'.$escape((string)$scoreAtLeast((float)$r['pod'], $podBands)).'%</div>').'<details class="metric-detail"><summary>';
            $podDetails .= $r['pod'] === null ? '—' : '<span data-perf-style="display:inline-block;padding:5px 8px;border-radius:5px;'.($rowShade('pod', (float)$r['pod']) !== null ? 'background:'.$escape((string)$rowShade('pod', (float)$r['pod'])).';color:#17314e' : ((float)$r['pod'] < $podColors['red_below'] ? 'background:'.$podColors['red_color'].';color:#9c0006' : ((float)$r['pod'] > $podColors['green_above'] ? 'background:'.$podColors['green_color'].';color:#006100' : 'background:'.$podColors['amber_color'].';color:#9c5700'))).'">'.$cell($r['pod'], '%').'</span>';
            $podDetails .= ' ▾</summary><div class="metric-detail-panel pod-detail-panel perf-style-84a435436f"><div class="perf-style-1da918d727"><strong>POD report details</strong><span class="perf-style-10898c41b9">'.$escape($user->getDisplayName()).'</span></div>';
            if ($podReport) {
                $podDetails .= '<div class="perf-style-6666ceb8f9">';
                foreach (['opportunities'=>'Opportunities','success'=>'Success','bypass'=>'Bypass','rejects'=>'Rejects','blurry_photo'=>'Blurry photo','no_package'=>'No package','package_in_car'=>'Package in car','package_too_close'=>'Package too close','photo_too_dark'=>'Photo too dark'] as $key => $label) {
                    $podDetails .= '<div class="perf-style-893dcf53dc"><div class="perf-style-64bb663393">'.$escape($label).'</div><strong>'.(int)$podReport[$key].'</strong></div>';
                }
                $podDetails .= '</div>';
            } else {
                $podDetails .= '<p>No imported POD details for this Transporter ID in the selected week.</p>';
            }
            $podDetails .= '</div></details>';
            $netTotal = $netradynePenalty($net);
            $netColor = $netTotal < 0 ? '#b42335' : '#087a39';
            $netBackground = $netTotal < 0 ? '#ffe2e6' : '#c9f2d5';
            $netDetails = '<details class="metric-detail"><summary><span class="netradyne-total" data-perf-style="display:inline-block;padding:4px 9px;border-radius:7px;font-weight:700;color:'.$netColor.';background:'.$netBackground.'">'.$netTotal.'%</span> ▾</summary><div class="metric-detail-panel"><strong>Netradyne violations</strong>';
            if ($full) {
                $netDetails .= '<form method="post" action="/driver-performance?tab=weekly&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('netradyne_weekly')->getValue()).'"><input type="hidden" name="netradyne_action" value="save"><input type="hidden" name="transporter_id" value="'.$escape($r['transporter_id']).'"><div class="netradyne-fields">';
                // Calculated penalties (read-only) above editable violation counts.
                foreach (['d'=>['D',2],'fd'=>['FD',2],'ss'=>['SS',2],'speed'=>['SPEED',2],'tl'=>['TL',2]] as $key=>$config) {
                    [$label, $multiplier] = $config;
                    $penalty = -max(0, (int)($net[$key] ?? 0)) * $netradyneMultipliers[$key];
                    $netDetails .= '<label>'.$label.'<input name="netradyne_penalty_display" type="number" data-penalty="'.$key.'" data-multiplier="'.$escape((string)$netradyneMultipliers[$key]).'" value="'.$penalty.'" readonly tabindex="-1" class="perf-style-fa338c0977"></label>';
                }
                $netDetails .= '</div><div class="perf-style-5a1ca0f54e">Violation counts (editable)</div><div class="netradyne-fields">';
                foreach (['d'=>'Distractions 25','fd'=>'Following Distance 5','ss'=>'Sign Signal 3','speed'=>'Speeding 15','tl'=>'Traffic Light 3'] as $key=>$label) $netDetails .= '<label>'.$label.'<input type="number" name="'.$key.'" min="-99999" max="99999" step="1" required value="'.$escape((string)($net[$key] ?? 0)).'"></label>';
                $netDetails .= '</div><small class="netradyne-save-status" aria-live="polite">Changes save automatically</small></form><script defer src="/netradyne-autosave.js?v=5"></script>';
            } else {
                foreach (['d'=>'D','fd'=>'FD','ss'=>'SS','speed'=>'SPEED','tl'=>'TL'] as $key=>$label) $netDetails .= '<span class="perf-style-b8f6142e9b">'.$label.': '.$escape((string)($net[$key] ?? '—')).'</span>';
            }
            $netDetails .= '</div></details>';
            $dwcScoreLabel = '';
            $dwcColour = '';
            if ($dwc) {
                $dwcValue = (float)$dwc['dwc_percent'];
                foreach ($dwcBands as [$minimum, $awarded]) if ($dwcValue >= (float)$minimum) { $dwcScoreLabel = '<div class="perf-style-308281ce8e">'.$escape((string)$awarded).'%</div>'; break; }
                $dwcColour = $dwcValue < (float)$dwcColors['red_below'] ? 'background:'.$escape((string)$dwcColors['red_color']).';color:#9c0006' : ($dwcValue <= (float)$dwcColors['green_above'] ? 'background:'.$escape((string)$dwcColors['amber_color']).';color:#9c5700' : 'background:'.$escape((string)$dwcColors['green_color']).';color:#006100');
            }
            if ($dwc) { $shade = $rowShade('dwc', $dwcValue); if ($shade !== null) $dwcColour = 'background:'.$escape($shade).';color:#17314e'; }
            $deliveryDetails = $dwcScoreLabel.'<details class="metric-detail"><summary><span data-perf-style="padding:4px;border-radius:5px;'.$dwcColour.'">'.($dwc ? number_format((float)$dwc['dwc_percent'], 2).'%' : '—').'</span> ▾</summary><div class="metric-detail-panel"><strong>Delivery details</strong><div>Total delivered: '.$cell($r['delivered']).'</div><div>Delivery completion rate: '.$cell($r['dcr'], '%').'</div><div>In-app delivery compliance: '.($dwc ? number_format((float)$dwc['iadc_percent'], 2).'%' : '—').'</div></div></details>';
            $ficoWeightDisplay = '';
            if (isset($weeklyFico[$user->getId()])) {
                $rawFico = (float)$weeklyFico[$user->getId()]['fisco_score'];
                foreach ($ficoBands as [$minimum, $awarded]) {
                    if ($rawFico >= (float)$minimum) {
                        $weighted = (float)$awarded;
                        $ficoWeightDisplay = '<div class="perf-style-308281ce8e">'.htmlspecialchars(rtrim(rtrim(number_format($weighted, 2, '.', ''), '0'), '.'), ENT_QUOTES, 'UTF-8').'%</div>';
                        break;
                    }
                }
            }
            $qty = ($r['delivered'] !== null && $r['dsc_dpmo'] !== null) ? ((float)$r['delivered'] / 1000000) * (float)$r['dsc_dpmo'] : null;
            $lorQty = ($r['delivered'] !== null && $r['lor_dpmo'] !== null) ? ((float)$r['delivered'] / 1000000) * (float)$r['lor_dpmo'] : null;
            $negativeFeedbackQty = ($r['delivered'] !== null && $r['cdf_dpmo'] !== null) ? ((float)$r['delivered'] / 1000000) * (float)$r['cdf_dpmo'] : null;
            $weeklyRows .= '<tr><td><label class="driver-select-label"><input name="driver_row_selection[]" type="checkbox" class="driver-row-select" value="'.$escape((string)$r['transporter_id']).'" aria-label="Select '.$escape($user->getDisplayName()).'"><span><strong>'.$escape($user->getDisplayName()).'</strong>'.($full ? '<div class="perf-style-cbd6cb7286">'.$escape($r['transporter_id']).'</div>' : '').'<button type="button" class="driver-report-open" data-user-id="'.$escape((string)$user->getId()).'" data-driver="'.$escape($user->getDisplayName()).'" data-phone="'.$escape((string)$user->getPhone()).'" data-id="'.$escape($r['transporter_id']).'" data-rank="'.(isset($rankedRows[$r['transporter_id']]['total']) ? $escape((string)($positions[$r['transporter_id']] ?? '?')) : '—').'" data-prior-rank="'.$escape((string)($priorPositions[$r['transporter_id']] ?? '')).'" data-prior-score="'.$escape((string)($priorRankedRows[$r['transporter_id']]['total'] ?? '')).'" data-score="'.(isset($rankedRows[$r['transporter_id']]['total']) ? $escape((string)$rankedRows[$r['transporter_id']]['total']) : '—').'" data-status="'.(isset($rankedRows[$r['transporter_id']]['total']) ? $escape(str_replace('_', ' ', $statusForScore((float)$rankedRows[$r['transporter_id']]['total'])[0])) : 'Pending').'" data-fico="'.$escape(isset($weeklyFico[$user->getId()]['fisco_score']) ? (string)(float)$weeklyFico[$user->getId()]['fisco_score'] : '—').'" data-fico-color="'.$escape((string)($rowShade('fico', (float)($weeklyFico[$user->getId()]['fisco_score'] ?? 0)) ?? ((float)($weeklyFico[$user->getId()]['fisco_score'] ?? 0) < (float)$ficoColors['red_below'] ? $ficoColors['red_color'] : ((float)($weeklyFico[$user->getId()]['fisco_score'] ?? 0) < (float)$ficoColors['amber_below'] ? $ficoColors['amber_color'] : $ficoColors['green_color'])))).'" data-fico-percent="'.$escape(($weeklyFico[$user->getId()]['fisco_score'] ?? null) !== null ? (string)$scoreAtLeast((float)$weeklyFico[$user->getId()]['fisco_score'], $ficoBands) : 'N/A').'" data-net-d="'.$escape((string)($net['d'] ?? 0)).'" data-net-fd="'.$escape((string)($net['fd'] ?? 0)).'" data-net-ss="'.$escape((string)($net['ss'] ?? 0)).'" data-net-speed="'.$escape((string)($net['speed'] ?? 0)).'" data-net-tl="'.$escape((string)($net['tl'] ?? 0)).'" data-dwc="'.$escape((string)($dwc['dwc_percent'] ?? '—')).'" data-dcr="'.$escape((string)($r['dcr'] ?? '—')).'" data-iadc="'.$escape((string)($dwc['iadc_percent'] ?? '—')).'" data-psb="'.$escape((string)($r['psb'] ?? '—')).'" data-dpmo="'.$escape((string)($r['dsc_dpmo'] ?? '—')).'" data-pod="'.$escape((string)($r['pod'] ?? '—')).'" data-cc="'.$escape((string)($r['cc'] ?? '—')).'" data-contact-made="'.$escape(isset($ccCounts[(string)$r['transporter_id']]) ? (string)$ccCounts[(string)$r['transporter_id']]['total_addresses'] : 'N/A').'" data-contacts-missed="'.$escape(isset($ccCounts[(string)$r['transporter_id']]) ? (string)max(0, (int)$ccCounts[(string)$r['transporter_id']]['total_addresses'] - (int)$ccCounts[(string)$r['transporter_id']]['total_contacts']) : 'N/A').'" data-ce="'.$escape((string)($r['ce'] ?? '—')).'" data-cdf="'.$escape((string)($r['cdf_dpmo'] ?? '—')).'" data-pod-bypass="'.$escape((string)(($podReport['bypass'] ?? null) ?? 'N/A')).'" data-pod-rejects="'.$escape((string)(($podReport['rejects'] ?? null) ?? 'N/A')).'" data-pod-opportunities="'.$escape((string)(($podReport['opportunities'] ?? null) ?? 'N/A')).'" data-pod-success="'.$escape((string)(($podReport['success'] ?? null) ?? 'N/A')).'" data-pod-blurry-photo="'.$escape((string)(($podReport['blurry_photo'] ?? null) ?? 'N/A')).'" data-pod-no-package="'.$escape((string)(($podReport['no_package'] ?? null) ?? 'N/A')).'" data-pod-package-in-car="'.$escape((string)(($podReport['package_in_car'] ?? null) ?? 'N/A')).'" data-pod-package-too-close="'.$escape((string)(($podReport['package_too_close'] ?? null) ?? 'N/A')).'" data-pod-photo-too-dark="'.$escape((string)(($podReport['photo_too_dark'] ?? null) ?? 'N/A')).'" data-cc-contacts="'.$escape((string)(($ccReport['total_contacts'] ?? null) ?? 'N/A')).'" data-cc-addresses="'.$escape((string)(($ccReport['total_addresses'] ?? null) ?? 'N/A')).'" data-qty="'.$escape((string)(($qty) ?? 'N/A')).'" data-negative-feedback-qty="'.$escape((string)($negativeFeedbackQty ?? 'N/A')).'" data-dwc-award="'.$escape((string)(($dwc ? $scoreAtLeast((float)$dwc['dwc_percent'], $dwcBands) : null) ?? 'N/A')).'" data-psb-award="'.$escape((string)(($r['psb'] !== null ? $scoreAtMost((float)$r['psb'], $psbBands) : null) ?? 'N/A')).'" data-dpmo-award="'.$escape((string)($r['dsc_dpmo'] !== null ? (static function (float $value) use ($dpmoBands): float { foreach ($dpmoBands as [$minimum, $points]) if ($value >= (float)$minimum) return (float)$points; return (float)end($dpmoBands)[1]; })((float)$r['dsc_dpmo']) : 'N/A')).'" data-pod-award="'.$escape((string)(($r['pod'] !== null ? $scoreAtLeast((float)$r['pod'], $podBands) : null) ?? 'N/A')).'" data-cc-award="'.$escape((string)(($r['cc'] !== null ? $scoreAtLeast((float)$r['cc'], $ccBands, true) : null) ?? 'N/A')).'" data-ce-award="'.$escape((string)(($r['ce'] !== null ? $scoreAtMost((float)$r['ce'], $ceBands, (float)$ceAbovePoints) : null) ?? 'N/A')).'" data-cdf-award="'.$escape((string)(($r['cdf_dpmo'] !== null ? $cdfPointsFor((float)$r['cdf_dpmo']) : null) ?? 'N/A')).'" data-pod-success-percent="'.$escape((string)(($podReport && $r['delivered'] !== null && (float)$r['delivered'] > 0 ? round(100 * (float)$podReport['success'] / (float)$r['delivered'], 2) : null) ?? 'N/A')).'" data-pod-opportunities-percent="'.$escape((string)(($podReport && $r['delivered'] !== null && (float)$r['delivered'] > 0 ? round(100 * (float)$podReport['opportunities'] / (float)$r['delivered'], 2) : null) ?? 'N/A')).'" data-lor-dpmo="'.$escape((string)($r['lor_dpmo'] ?? 'N/A')).'" data-lor-qty="'.$escape($lorQty === null ? 'N/A' : (string)round($lorQty)).'" data-delivered="'.$escape((string)($r['delivered'] ?? 'N/A')).'" data-dcr-award="N/A"   data-net="'.$netTotal.'">📄 Report</button></span></label></td><td><strong>Rank: '.(isset($rankedRows[$r['transporter_id']]['total']) ? $escape((string)($positions[$r['transporter_id']] ?? '?')) : '—').'</strong><div>Status: '.(isset($rankedRows[$r['transporter_id']]['total']) ? '<span data-perf-style="display:inline-block;padding:3px 7px;border-radius:5px;background:'.$statusForScore((float)$rankedRows[$r['transporter_id']]['total'])[1].';color:'.$statusForScore((float)$rankedRows[$r['transporter_id']]['total'])[2].';font-weight:600;font-size:12px">'.$escape(str_replace('_', ' ', $statusForScore((float)$rankedRows[$r['transporter_id']]['total'])[0])).'</span>' : 'Pending').'</div><div class="perf-style-86abdab991">Score: '.(isset($rankedRows[$r['transporter_id']]['total']) ? $escape(rtrim(rtrim(number_format($rankedRows[$r['transporter_id']]['total'], 2, '.', ''), '0'), '.')) : '—').' / 100</div><div class="perf-style-ee028ef084"><label class="perf-style-bb8f18a92e"><input type="checkbox" class="perf-style-ce2298683d" class="coaching-done" data-id="'.$escape($r['transporter_id']).'" '.(isset($coachingDone[$r['transporter_id']])?'checked ':'').($full?'':'disabled ').'> <span>Coaching done</span></label></div></td><td>'.(isset($weeklyFico[$user->getId()]) ? $ficoWeightDisplay.'<span data-perf-style="display:inline-block;padding:6px 9px;border-radius:5px;background:'.($rowShade('fico', (float)$weeklyFico[$user->getId()]['fisco_score']) ?? ((float)$weeklyFico[$user->getId()]['fisco_score'] < (float)$ficoColors['red_below'] ? $ficoColors['red_color'] : ((float)$weeklyFico[$user->getId()]['fisco_score'] < (float)$ficoColors['amber_below'] ? $ficoColors['amber_color'] : $ficoColors['green_color']))).';color:'.((float)$weeklyFico[$user->getId()]['fisco_score'] < (float)$ficoColors['red_below'] ? '#9c0006' : ((float)$weeklyFico[$user->getId()]['fisco_score'] < (float)$ficoColors['amber_below'] ? '#9c5700' : '#006100')).'">'.$cell((float)$weeklyFico[$user->getId()]['fisco_score']).'</span>' : '—').'</td><td>'.$netDetails.'</td><td>'.$deliveryDetails.'</td><td>'.($r['psb'] === null ? '—' : '<div class="perf-style-5a9033ce52">'.$escape((string)(static function(float $value) use ($psbBands): float { foreach ($psbBands as [$maximum, $points]) if ($value <= $maximum) return $points; return 0; })((float)$r['psb'])).'%'.'</div><span data-perf-style="display:inline-block;padding:5px 8px;border-radius:5px;background:'.($rowShade('psb', (float)$r['psb']) ?? ((float)$r['psb'] == 0 ? $psbColors['green_color'] : $psbColors['red_color'])).';color:'.((float)$r['psb'] == 0 ? '#006100' : '#9c0006').'">'.$cell($r['psb']).'</span>').'</td><td>'.($r['dsc_dpmo'] === null ? '—' : '<div class="perf-style-e212ce6660">'.$escape((string)(static function (float $value) use ($dpmoBands): float { foreach ($dpmoBands as [$minimum, $points]) if ($value >= $minimum) return $points; return (float)end($dpmoBands)[1]; })((float)$r['dsc_dpmo'])).'%</div><span data-perf-style="display:inline-block;padding:5px 8px;border-radius:5px;'.($rowShade('dpmo', (float)$r['dsc_dpmo']) !== null ? 'background:'.$escape((string)$rowShade('dpmo', (float)$r['dsc_dpmo'])).';color:#17314e' : ((float)$r['dsc_dpmo'] <= $dpmoColors['green_at_or_below'] ? 'background:'.$dpmoColors['green_color'].';color:#006100' : (((float)$r['dsc_dpmo'] > $dpmoColors['green_at_or_below'] && (float)$r['dsc_dpmo'] <= $dpmoColors['red_above']) ? 'background:'.$dpmoColors['amber_color'].';color:#9c5700' : ((float)$r['dsc_dpmo'] > $dpmoColors['red_above'] ? 'background:'.$dpmoColors['red_color'].';color:#9c0006' : '')))).'">'.$cell($r['dsc_dpmo']).'</span><div class="perf-style-86abdab991">Qty = <strong class="perf-style-d7a1bf7a63">'.($qty === null ? '—' : rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.')).'</strong></div>').'</td><td>'.$podDetails.'</td><td>'.$ccDetails.'</td><td>'.($r['ce'] === null ? '<span data-perf-style="display:inline-block;padding:5px 8px;border-radius:5px;background:'.$escape((string)$ceColors['missing_color']).';color:#006100">—</span>' : '<div class="perf-style-e212ce6660">'.$escape((string)(static function(float $v) use ($ceBands,$ceAbovePoints): float { foreach ($ceBands as [$maximum,$points]) if ($v <= $maximum) return $points; return $ceAbovePoints; })((float)$r['ce'])).'%</div><span data-perf-style="display:inline-block;padding:5px 8px;border-radius:5px;background:'.$escape((string)($rowShade('ce', (float)$r['ce']) ?? ((float)$r['ce'] == 0 ? $ceColors['zero_color'] : $ceColors['positive_color']))).';color:'.((float)$r['ce'] == 0 ? '#006100' : '#9c0006').'">'.$cell($r['ce']).'</span>').'</td><td>'.($r['cdf_dpmo'] === null ? '—' : '<div class="perf-style-b178453907">'. $escape((string)$cdfPointsFor((float)$r['cdf_dpmo'])).'%</div><span data-perf-style="display:inline-block;border-radius:5px;padding:5px 8px;background:'.$escape((string)($rowShade('cdf',(float)$r['cdf_dpmo']) ?? $cdfShadeFor($cdfPointsFor((float)$r['cdf_dpmo'])))).';color:#17314e">'.$cell($r['cdf_dpmo']).'</span><div class="perf-style-741bca65be">Qty = <strong class="perf-style-d7a1bf7a63">'.($negativeFeedbackQty === null ? '—' : rtrim(rtrim(number_format($negativeFeedbackQty, 2, '.', ''), '0'), '.')).'</strong></div>').'</td></tr>';
        }
        $dspUnmatched = $full ? $db->fetchAllAssociative('SELECT u.transporter_id, u.ignored FROM driver_dsp_unmatched u WHERE u.week_start = ? AND NOT EXISTS (SELECT 1 FROM driver_dsp_summary r WHERE r.week_start = u.week_start AND r.transporter_id = u.transporter_id) ORDER BY u.transporter_id', [$weekStart]) : [];
        $dspIgnored = count(array_filter($dspUnmatched, static fn($r) => (bool)$r['ignored']));
        $dspPanel = '<section class="card"'.($tab !== 'dsp' ? ' hidden' : '').'><h2>DSP Weekly Summary</h2><p class="muted">Week beginning '.$escape($weekStart).' · '.$dspCount.' drivers imported'.($full && $dspIgnored ? ' · '.$dspIgnored.' IDs ignored' : '').'</p>'.($tab === 'dsp' ? $notice : '');
        if ($full && $dspUnmatched) {
            $allIgnored = $dspIgnored === count($dspUnmatched);
            $dspPanel .= $allIgnored ? '<details><summary>'.$dspIgnored.' IDs ignored · View ignored IDs</summary>' : '';
            $dspPanel .= '<div class="perf-style-d4c443c814"><strong>Unmatched Transporter IDs</strong><p>Create missing drivers in <a href="/admin/users?view=drivers">Driver Management</a> and reimport, or ignore IDs.</p><form method="post" action="/driver-performance?tab=dsp&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('dsp_import')->getValue()).'"><input type="hidden" name="dsp_action" value="ignore"><table><tr><th>Transporter ID</th><th>Status</th><th>Ignore</th><th>Delete</th></tr>';
            foreach ($dspUnmatched as $r) $dspPanel .= '<tr><td>'.$escape($r['transporter_id']).'</td><td>'.$escape($unmatchedStatus($r)).'</td><td><input type="checkbox" name="ignore_ids[]" value="'.$escape($r['transporter_id']).'"'.($r['ignored'] ? ' checked' : '').'></td><td><input type="checkbox" name="delete_ids[]" value="'.$escape($r['transporter_id']).'"></td></tr>';
            $dspPanel .= '</table><button type="submit">Save ignore choices</button> <button type="submit" name="dsp_action" value="delete" data-perf-confirm-unmatched="1" class="perf-style-353f94387d">Delete selected</button></form></div>'.($allIgnored ? '</details>' : '');
        }
        if ($full) $dspPanel .= '<details class="dsp-import"><summary>Import / Replace DSP Summary <span class="perf-style-88159e98b2">PDF · Excel · HTML · CSV</span></summary><form id="dsp-import-form" method="post" enctype="multipart/form-data" action="/driver-performance?tab=dsp&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('dsp_import')->getValue()).'"><div class="dsp-import-grid"><div><label class="dsp-dropzone" id="dsp-drop" for="dsp-file"><span class="dsp-upload-icon">⇧</span><strong>Choose a report or drag it here</strong><span class="dsp-muted">PDF, XLSX, MHTML, HTML, CSV or TXT · Max 20 MB</span><span class="dsp-file-label" id="dsp-file-name">No file selected</span></label><input id="dsp-file" type="file" name="dsp_file" accept=".pdf,.xlsx,.mhtml,.mht,.html,.htm,.csv,.txt" class="dsp-file-input"></div><div><label class="dsp-paste-label" for="dsp-paste">Or paste report data</label><textarea id="dsp-paste" name="dsp_paste" rows="6" maxlength="2000000" placeholder="Paste DSP Summary HTML, CSV or tab-separated rows here"></textarea><p class="dsp-muted">Use a file or pasted data — only one is needed.</p></div></div><div class="dsp-import-actions"><span class="dsp-muted">Import replaces the selected week’s DSP Summary records.</span><button class="dsp-import-button" type="submit">Import DSP Summary</button></div></form></details><script src="/driver-performance-inline-eaf30b1d630e.js"></script>';
        $dspPanel .= $dspCount ? '<div class="perf-style-a632715a5f"><table><thead><tr><th>#</th><th>Driver name</th><th>Delivered</th><th>DCR</th><th>DSC DPMO</th><th>LoR DPMO</th><th>POD</th><th>CC</th><th>CE</th><th>CDF DPMO</th><th>PSB</th></tr></thead><tbody>'.$dspRows.'</tbody></table></div>' : '<p>No DSP Summary report imported for this week.</p>';
        $dspPanel .= '</section>';
        $rulesPanel = '<div id="scoring-rules-editor" class="perf-style-762a6f16c1"><h3>Scoring Rules · Category Weights</h3><p class="muted">Categories follow the Weekly Reports columns from left to right. Netradyne violations have no separate maximum-point row; QTY is calculated beneath DSC DPMO. FICO, DWC, DPMO and POD formulas are configurable; full driver totals remain pending.</p>'.($request->query->get('rules_saved') === '1' ? '<p class="perf-style-9cdf81d40c">Scoring weights saved.</p>' : '');
        foreach ($request->getSession()->getFlashBag()->get('score_error') as $scoreError) $rulesPanel .= '<p role="alert" class="perf-style-423f7f89fa">'.$escape($scoreError).'</p>';
        if ($full) $rulesPanel .= '<form method="post" action="/driver-performance?tab=weekly&amp;week='.$escape($weekStart).'"><input type="hidden" name="score_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('performance_rules')->getValue()).'">';
        $rulesPanel .= '<table><thead><tr><th>Category</th><th>Maximum points</th></tr></thead><tbody>';
        foreach ($scoreRules as $key => $rule) {
            $rulesPanel .= '<tr><td>'.$escape($rule['label']).($key === 'dsc_dpmo' && $full ? ' <button type="button" data-perf-open-dialog="dpmo-formula-dialog" class="perf-style-6c77346c6a">Edit DPMO formula</button>' : '').($key === 'cdf_dpmo' && $full ? ' <button type="button" data-perf-open-dialog="cdf-formula-dialog" class="perf-style-6c77346c6a">Edit Negative Feedback formula</button>' : '').($key === 'ce' && $full ? ' <button type="button" data-perf-open-dialog="ce-formula-dialog" class="perf-style-6c77346c6a">Edit CE formula</button>' : '').($key === 'cc' && $full ? ' <button type="button" data-perf-open-dialog="cc-formula-dialog" class="perf-style-6c77346c6a">Edit CC formula</button>' : '').($key === 'pod' && $full ? ' <button type="button" data-perf-open-dialog="pod-formula-dialog" class="perf-style-6c77346c6a">Edit POD formula</button>' : '').($key === 'psb' && $full ? ' <button type="button" data-perf-open-dialog="psb-formula-dialog" class="perf-style-6c77346c6a">Edit PSB formula</button>' : '').($key === 'dwc' && $full ? ' <button type="button" data-perf-open-dialog="dwc-formula-dialog" class="perf-style-6c77346c6a">Edit DWC formula</button>' : '').($key === 'fico' && $full ? ' <button type="button" data-perf-open-dialog="fico-formula-dialog" class="perf-style-6c77346c6a">Edit FICO formula</button>' : '').'</td><td>'.($full ? '<input class="perf-style-7b463e1d95" type="number" min="0" max="100" step="0.5" required name="maximum['.$escape($key).']" value="'.$escape((string)$rule['maximum']).'">' : $escape((string)$rule['maximum'])).'</td></tr>';
        }
        $rulesPanel .= '</tbody></table>';
        if ($full) $rulesPanel .= '<p id="weights-total" role="status" aria-live="polite" class="perf-style-a2e37044cf">Total: 100 / 100 ✓</p><p id="weights-warning" class="perf-style-87bc93e419" hidden>The total must equal 100 before saving.</p><button id="weights-save" type="submit" class="perf-style-e018365af9">Save scoring weights</button></form>';
        $rulesPanel .= '</div>';
        $netradyneRulesPanel = '<div class="perf-style-efa06017dc"><h3>Netradyne violation multipliers</h3><p class="muted">Each violation count is multiplied by its value below. The resulting total is deducted in percentage points from the driver score. Changes recalculate existing weekly reports.</p>';
        if ($request->query->get('netradyne_rules_saved') === '1') $netradyneRulesPanel .= '<p class="perf-style-3f8a2ca06b">Netradyne multipliers saved.</p>';
        if ($full) $netradyneRulesPanel .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="netradyne_rules_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('netradyne_multiplier_rules')->getValue()).'">';
        $netradyneRulesPanel .= '<div class="perf-style-e17d2dc1fe">';
        foreach (['d'=>'Distractions','fd'=>'Following distance','ss'=>'Sign signal','speed'=>'Speeding','tl'=>'Traffic light'] as $key=>$label) {
            $netradyneRulesPanel .= '<label class="perf-style-0a55074bb0">'.$escape($label).'<input type="number" name="netradyne_multiplier['.$key.']" min="0" max="100" step="0.01" required value="'.$escape((string)$netradyneMultipliers[$key]).'" class="perf-style-528196f51a"'.(!$full?' disabled':'').'></label>';
        }
        $netradyneRulesPanel .= '</div>';
        if ($full) $netradyneRulesPanel .= '<button type="submit" class="perf-style-42dfa5b4d6">Save Netradyne multipliers</button></form>';
        $netradyneRulesPanel .= '</div>';
        $reportPanel = '<section class="card"'.($tab !== 'report' ? ' hidden' : '').'><h2>Report layout</h2><p class="muted">Change report headings and reorder individual delivery metrics. Scoring is unchanged.</p>';
        if ($request->query->get('saved') === '1') $reportPanel .= '<p class="perf-style-3f8a2ca06b">Report layout saved.</p>';
        if ($full) $reportPanel .= '<form method="post" action="/driver-performance?tab=report&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('report_layout')->getValue()).'">';
        foreach (['title'=>'Report title','safety'=>'Safety heading','delivery'=>'Delivery quality heading'] as $key=>$label) {
            $reportPanel .= '<label class="perf-style-97b1c119b5">'.$label.'<input class="perf-style-e8a52fbd98" name="'.$key.'" maxlength="120" required value="'.$escape($reportLayout[$key]).'"'.(!$full?' readonly':'').'></label>';
        }
        $reportPanel .= '<h3>Safety metric order</h3><p class="muted">Edit metric names, reorder rows and configure improvement messages. Safety scoring remains unchanged.</p><ol id="safety-metric-order" class="perf-style-dd8f7d1b1d">';
        foreach ($safetyOrder as $key) {
            $reportPanel .= '<li data-metric="'.$escape($key).'" class="perf-style-58f5ea27a9"><input type="hidden" name="safety_order[]" value="'.$escape($key).'"><input aria-label="Safety metric name" name="metric_labels['.$escape($key).']" maxlength="120" required value="'.$escape($metricLabels[$key] ?? $safetyMetrics[$key]).'" class="perf-style-595270c071"'.(!$full?' readonly':'').'>'.' <label class="perf-style-2a60f2ccdc"><input type="checkbox" name="metric_indents['.$escape($key).']" value="1"'.(!empty($reportLayout['indents'][$key])?' checked':'').(!$full?' disabled':'').'> Indent label</label>'.($full?' <button type="button" class="report-up">↑</button> <button type="button" class="report-down">↓</button>':'').'</li>';
        }
        $reportPanel .= '</ol>';
        $reportPanel .= '<h3>Delivery quality metric order</h3><p class="muted">Use the arrows to move any metric. Subsection headings are removed from the report.</p><ol id="report-metric-order" class="perf-style-dd8f7d1b1d">';
        foreach ($reportLayout['order'] as $key) $reportPanel .= '<li data-metric="'.$escape($key).'" class="perf-style-58f5ea27a9"><input type="hidden" name="order[]" value="'.$escape($key).'"><input aria-label="Metric name" name="metric_labels['.$escape($key).']" maxlength="120" required value="'.$escape($metricLabels[$key] ?? $reportMetrics[$key]).'" class="perf-style-595270c071"'.(!$full?' readonly':'').'>'.' <label class="perf-style-2a60f2ccdc"><input type="checkbox" name="metric_indents['.$escape($key).']" value="1"'.(!empty($reportLayout['indents'][$key])?' checked':'').(!$full?' disabled':'').'> Indent label</label>'.($full?' <button type="button" class="report-up" aria-label="Move up">↑</button> <button type="button" class="report-down" aria-label="Move down">↓</button>':'').'</li>';
        $metricFocusLabels = ['DCR'=>'dcr','DPMO'=>'dpmo','POD'=>'pod','CE'=>'ce','DWC'=>'dwc','CC'=>'cc','DEX'=>'cdf','PBS'=>'psb','NOA'=>'contactMade','FICO'=>'fico','NET'=>'netSpeed'];
        $linkedMetricKeys = array_values(array_filter(array_map(static fn($trigger) => $metricFocusLabels[$trigger] ?? null, $focusTriggers)));
        foreach (array_merge($reportMetrics, $safetyMetrics) as $metricKey => $metricName) {
            if (in_array($metricKey, $linkedMetricKeys, true) || in_array($metricKey, ['netD','netFd','netSs','netTl'], true)) continue;
            $stored = is_array($metricFocus[$metricKey] ?? null) ? $metricFocus[$metricKey] : [];
            $heading = (string)($stored['heading'] ?? $metricName);
            $message = (string)($stored['message'] ?? '');
            $reportPanel .= '<div class="metric-focus-editor" data-for-metric="'.$escape($metricKey).'" class="perf-style-791b526bea"><label class="perf-style-2a1b75c911">Focus heading<input name="metric_focus['.$escape($metricKey).'][heading]" value="'.$escape($heading).'" maxlength="80" required class="perf-style-7e0f183468"></label><label class="perf-style-5067df754f">Improvement message<textarea name="metric_focus['.$escape($metricKey).'][message]" maxlength="1200" rows="3" class="perf-style-489f449508" placeholder="Leave blank to hide recommendation">'.$escape($message).'</textarea></label><small>Linked to: '.$escape($metricName).'</small></div>';
        }
        $reportPanel .= '<script src="/driver-performance-inline-4f492a233cd0.js"></script>';
        $reportPanel .= '</ol>';
        if ($full) $reportPanel .= '<button type="submit">Save report layout</button></form><script src="/driver-performance-inline-48f1255037f7.js"></script>';
        $rulesTabPanel = '<section class="card"'.($tab !== 'rules' ? ' hidden' : '').'><h2>Rules</h2>';
        $dwcDialog = '<dialog id="dwc-formula-dialog" class="perf-style-54285222e0"><h3>DWC scoring formula</h3><p class="muted">Enter minimum DWC percentages and manually awarded points. Values are never automatically scaled. Save the category maximum first.</p>';
        if ($full) $dwcDialog .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="dwc_formula_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('dwc_formula_rules')->getValue()).'">';
        $dwcDialog .= '<div class="perf-style-e04a751a8f"><table><thead><tr><th>Minimum DWC %</th><th>Points / '.$escape((string)$scoreRules['dwc']['maximum']).'</th><th>Colour</th></tr></thead><tbody>';
        foreach ($dwcBands as $i => [$minimum, $awarded]) {
            $dwcDialog .= '<tr><td><input type="number" name="dwc_threshold['.$i.']" min="0" max="100" step="any" required value="'.$escape((string)$minimum).'" class="perf-style-7b463e1d95"'.(!$full ? ' disabled' : '').'></td><td><input type="number" name="dwc_points['.$i.']" min="0" max="'.$escape((string)$scoreRules['dwc']['maximum']).'" step="any" required value="'.$escape((string)$awarded).'" class="perf-style-7b463e1d95"'.(!$full ? ' disabled' : '').'></td><td><input type="color" name="condition_color['.$i.']" value="'.$escape((string)$conditionColours['dwc'][(string)(float)$minimum]).'" class="perf-style-d9e5ae8c7f"'.(!$full ? ' disabled' : '').'></td></tr>';
        }
        $dwcDialog .= '</tbody></table></div>';
        if ($full) $dwcDialog .= '<div class="perf-style-0fcd2e0a80"><button type="button" id="dwc-add">＋ Add row</button><button type="button" id="dwc-remove">− Remove last</button><button type="button" id="dwc-sort">↕ Sort scores</button></div><div class="perf-style-3efc9879ca"><button type="submit" class="perf-style-4713348b16">Save DWC formula</button><button type="button" data-perf-close-dialog="dwc-formula-dialog">Close</button></div></form>';
        else $dwcDialog .= '<button type="button" data-perf-close-dialog="dwc-formula-dialog">Close</button>';
        $dwcDialog .= '<div class="dwc-colour-editor" hidden class="perf-style-a188aec46b"><h4>DWC · Conditional colours</h4><p class="muted">Set colour thresholds and choose the colours shown in Weekly Reports.</p><div class="dwc-colour-preview perf-style-3de8f987ba">';
        foreach (['red' => 'Red', 'amber' => 'Amber', 'green' => 'Green'] as $shade => $label) $dwcDialog .= '<span data-colour="'.$shade.'" data-perf-style="padding:9px 12px;border-radius:7px;background:'.$escape((string)$dwcColors[$shade.'_color']).';color:#17314e">'.$label.'</span>';
        $dwcDialog .= '</div>';
        if ($full) {
            $dwcDialog .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="dwc_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('dwc_rules')->getValue()).'"><div class="dwc-colour-grid"><label>Red below (%)<input type="number" name="dwc_red" min="0" max="100" step="any" required value="'.$escape((string)$dwcColors['red_below']).'"></label><label>Green above (%)<input type="number" name="dwc_green" min="0" max="100" step="any" required value="'.$escape((string)$dwcColors['green_above']).'"></label></div><div class="dwc-colour-grid">';
            foreach (['red' => 'Red', 'amber' => 'Amber', 'green' => 'Green'] as $shade => $label) $dwcDialog .= '<label>'.$label.' colour<input type="color" name="dwc_'.$shade.'_color" value="'.$escape((string)$dwcColors[$shade.'_color']).'"></label>';
            $dwcDialog .= '</div><button type="submit" class="perf-style-2a806319bb">Save DWC colours</button></form>';
        }
        $dwcDialog .= '</div>';
        $dwcDialog .= '</dialog><script src="/driver-performance-inline-67bb6554e542.js"></script>';
        $ficoColourPanel = '<div class="perf-style-418ed64086"><span data-perf-style="background:'.$escape((string)$ficoColors['red_color']).';color:#9c0006;padding:12px;border-radius:7px">Red: below '.$escape((string)$ficoColors['red_below']).'</span><span data-perf-style="background:'.$escape((string)$ficoColors['amber_color']).';color:#9c5700;padding:12px;border-radius:7px">Amber: '.$escape((string)$ficoColors['red_below']).' to below '.$escape((string)$ficoColors['amber_below']).'</span><span data-perf-style="background:'.$escape((string)$ficoColors['green_color']).';color:#006100;padding:12px;border-radius:7px">Green: '.$escape((string)$ficoColors['amber_below']).' and above</span></div>';
        if ($full) $ficoColourPanel .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="fico_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('fico_color_rules')->getValue()).'"><label>Red if FICO below <input type="number" min="0" max="849" step="0.5" name="red_below" required value="'.$escape((string)$ficoColors['red_below']).'"></label><label>Amber if FICO below <input type="number" min="0.5" max="850" step="0.5" name="amber_below" required value="'.$escape((string)$ficoColors['amber_below']).'"></label><div class="fico-colour-pickers">'.implode('', array_map(static fn($shade) => '<label>'.ucfirst($shade).' colour<input type="color" name="fico_'.$shade.'_color" value="'.htmlspecialchars((string)$ficoColors[$shade.'_color'], ENT_QUOTES, 'UTF-8').'"></label>', ['red','amber','green'])).'</div><button type="submit" class="perf-style-9fd41ce710">Save FICO colour rules</button></form>';
        $ficoColourPanel = '<div class="fico-colour-editor perf-style-462d195dbb"><h4 class="perf-style-df671843ff">FICO · Conditional colours</h4><p class="muted">Colours apply to weekly FICO scores. Blank scores remain uncoloured.</p>'.($request->query->get('fico_saved') === '1' ? '<p class="perf-style-3f8a2ca06b">FICO colour rules saved.</p>' : '').$ficoColourPanel.'</div>';
        $formulaDialog = '<dialog id="fico-formula-dialog" class="perf-style-5cb901fc55"><h3>FICO / Ementor scoring formula</h3><p class="muted">For each minimum FICO score, award the corresponding points. Below the lowest threshold, no points are awarded. Formula points are manually entered and never rescaled when category weights change. Save category weights first; any formula points above the saved maximum must be edited manually.</p>';
        if ($full) $formulaDialog .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="fico_formula_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('fico_formula_rules')->getValue()).'">';
        $formulaDialog .= '<div class="perf-style-e04a751a8f"><table><thead><tr><th>Minimum FICO</th><th>Points / '.$escape((string)$scoreRules['fico']['maximum']).'</th><th>Colour</th></tr></thead><tbody>';
        foreach ($ficoBands as $i => [$minimum, $awarded]) {
            $formulaDialog .= '<tr><td>'.($full ? '<input type="number" min="0" max="850" step="1" required name="fico_threshold['.$i.']" value="'.$escape((string)$minimum).'" class="perf-style-7b463e1d95">' : $escape((string)$minimum)).'</td><td>'.($full ? '<input type="number" min="0" max="'.$escape((string)$scoreRules['fico']['maximum']).'" step="any" required name="fico_points['.$i.']" value="'.$escape((string)(float)$awarded).'" class="perf-style-7b463e1d95">' : $escape((string)$awarded)).'</td><td><input type="color" name="condition_color['.$i.']" value="'.$escape((string)$conditionColours['fico'][(string)(float)$minimum]).'" class="perf-style-d9e5ae8c7f"'.(!$full ? ' disabled' : '').'></td></tr>';
        }
        $formulaDialog .= '</tbody></table></div>';
        if ($full) $formulaDialog .= '<div class="fico-formula-actions"><button type="button" class="fico-action fico-action-add" id="fico-add-row">＋ Add row</button><button type="button" class="fico-action fico-action-remove" id="fico-remove-row">− Remove last</button><button type="button" class="fico-action fico-action-sort" id="fico-sort-rows">↕ Sort scores</button></div><p class="muted perf-style-6cb285c61d">Use Sort scores to order thresholds. Save requires descending FICO scores.</p>';
        if ($full) $formulaDialog .= '<div class="perf-style-6047c6b3e5"><button type="submit" class="perf-style-809a58476d">Save FICO formula</button><button type="button" data-perf-close-dialog="fico-formula-dialog" class="perf-style-b6ca58bfba">Close</button></div></form>';
        else $formulaDialog .= '<form method="dialog"><button class="perf-style-61acd0f653">Close</button></form>';
        
        $formulaDialog .= '</dialog>';
        $formulaDialog .= '<script src="/driver-performance-inline-ac29207be8ac.js"></script>';
        $psbDialog = '<dialog id="psb-formula-dialog" class="perf-style-f9606e9a78"><h3>PSB · Collection Fails /100 stops</h3><p class="muted">Each row awards its points when PSB is at or below that threshold. The lowest matching threshold applies; above the highest threshold, 0 points. Your existing rule is preserved until you save changes.</p>';
        if ($full) $psbDialog .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="psb_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('psb_rules')->getValue()).'">';
        $psbDialog .= '<h4>Scoring conditions</h4><div class="perf-style-2234e7120f"><table><thead><tr><th>PSB at or below</th><th>Points / '.$escape((string)$scoreRules['psb']['maximum']).'</th><th>Colour</th></tr></thead><tbody id="psb-bands-body">';
        foreach ($psbBands as $i => [$threshold, $points]) $psbDialog .= '<tr><td><input type="number" name="psb_threshold['.$i.']" min="0" step="any" required value="'.$escape((string)$threshold).'" class="perf-style-1747661c50"'.(!$full ? ' disabled' : '').'></td><td><input type="number" name="psb_points['.$i.']" min="0" max="'.$escape((string)$scoreRules['psb']['maximum']).'" step="any" required value="'.$escape((string)$points).'" class="perf-style-1747661c50"'.(!$full ? ' disabled' : '').'></td><td><input type="color" name="condition_color['.$i.']" value="'.$escape((string)$conditionColours['psb'][(string)(float)$threshold]).'" class="perf-style-d9e5ae8c7f"'.(!$full ? ' disabled' : '').'></td></tr>';
        $psbDialog .= '</tbody></table></div>';
        if ($full) $psbDialog .= '<div class="psb-actions"><button type="button" id="psb-add">＋ Add row</button><button type="button" id="psb-remove">− Remove last</button><button type="button" id="psb-sort">↕ Sort thresholds</button></div>';
        $psbDialog .= '<section hidden class="psb-colour-section"><h4>Conditional colours</h4><p class="muted">Colours are independent of scoring: PSB = 0 is green; PSB &gt; 0 is red.</p><div class="psb-colour-grid">';
        foreach (['green' => 'Green · PSB = 0', 'red' => 'Red · PSB > 0'] as $shade => $label) $psbDialog .= '<label>'.$label.'<input type="color" name="psb_'.$shade.'_color" value="'.$escape((string)$psbColors[$shade.'_color']).'"'.(!$full ? ' disabled' : '').'></label>';
        $psbDialog .= '</div></section>';
        if ($full) $psbDialog .= '<div class="psb-actions"><button type="submit" class="psb-save">Save PSB formula and colours</button><button type="button" data-perf-close-dialog="psb-formula-dialog">Close</button></div></form>';
        else $psbDialog .= '<button type="button" data-perf-close-dialog="psb-formula-dialog">Close</button>';
        $psbDialog .= '</dialog><script src="/driver-performance-inline-235a53e096c5.js"></script>';
        foreach ($request->getSession()->getFlashBag()->get('psb_error') as $error) $psbDialog .= '<p role="alert" class="perf-style-ef667ec071">'.$escape($error).'</p>';
        $ceDialog = '<dialog id="ce-formula-dialog" class="perf-style-eaf2bc44f0"><h3>CE · Escalations scoring formula and colours</h3><p class="muted">Award points at or below each CE threshold, lowest matching first. The default is 15 points at zero, 0 points above zero. Colours are independent of scoring.</p>';
        if ($full) $ceDialog .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="ce_action" value="save"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('ce_formula_rules')->getValue()).'">';
        $ceDialog .= '<h4>Scoring conditions</h4><div class="perf-style-d4276f7147"><table><thead><tr><th>CE at or below</th><th>Points / '.$escape((string)$scoreRules['ce']['maximum']).'</th><th>Colour</th></tr></thead><tbody id="ce-bands-body">';
        foreach ($ceBands as $i => [$threshold,$points]) $ceDialog .= '<tr><td><input type="number" name="ce_threshold['.$i.']" min="0" step="any" required value="'.$escape((string)$threshold).'" class="perf-style-7da2b6cfa2"'.(!$full ? ' disabled' : '').'></td><td><input type="number" name="ce_points['.$i.']" min="0" max="'.$escape((string)$scoreRules['ce']['maximum']).'" step="any" required value="'.$escape((string)$points).'" class="perf-style-7da2b6cfa2"'.(!$full ? ' disabled' : '').'></td><td><input type="color" name="condition_color['.$i.']" value="'.$escape((string)$conditionColours['ce'][(string)(float)$threshold]).'" class="perf-style-d9e5ae8c7f"'.(!$full ? ' disabled' : '').'></td></tr>';
        $ceDialog .= '</tbody></table></div>';
        if ($full) $ceDialog .= '<div class="ce-actions"><button type="button" id="ce-add">＋ Add row</button><button type="button" id="ce-remove">− Remove last</button><button type="button" id="ce-sort">↕ Sort thresholds</button></div>';
        $ceDialog .= '<label class="perf-style-70e255b7a5">Points above highest threshold<input type="number" name="ce_above_points" min="0" max="'.$escape((string)$scoreRules['ce']['maximum']).'" step="any" required value="'.$escape((string)$ceAbovePoints).'" class="perf-style-f909d8a9d0"'.(!$full ? ' disabled' : '').'></label><section hidden class="ce-colour-section"><h4>Conditional colours</h4><p class="muted">Zero = green, positive CE = red, missing/dash = green. Choose colours below.</p><div class="ce-colour-preview"><span data-perf-style="background:'.$escape((string)$ceColors['positive_color']).'">Red</span><span data-perf-style="background:'.$escape((string)$ceColors['zero_color']).'">Green</span><span data-perf-style="background:'.$escape((string)$ceColors['missing_color']).'">Missing</span></div><div class="ce-colour-grid">';
        foreach (['zero' => 'Zero (green)', 'positive' => 'Above zero (red)', 'missing' => 'Missing / dash (green)'] as $shade => $label) $ceDialog .= '<label>'.$label.'<input type="color" name="ce_'.$shade.'_color" value="'.$escape((string)$ceColors[$shade.'_color']).'"'.(!$full ? ' disabled' : '').'></label>';
        $ceDialog .= '</div></section>';
        if ($full) $ceDialog .= '<div class="ce-actions"><button type="submit" class="ce-save">Save CE formula and colours</button><button type="button" data-perf-close-nearest-dialog="1">Close</button></div></form>';
        else $ceDialog .= '<button type="button" data-perf-close-nearest-dialog="1">Close</button>';
        $ceDialog .= '</dialog><script src="/driver-performance-inline-1962f0cbb8ea.js"></script>';
        $metricDialogs = '';
        foreach (['dpmo' => [$dpmoBands, $dpmoColors, 'DSC DPMO', 'dsc_dpmo'], 'pod' => [$podBands, $podColors, 'POD', 'pod'], 'cc' => [$ccBands, $ccColors, 'CC', 'cc'], 'cdf' => [$cdfBands, $cdfColors, 'Negative Feedback', 'cdf_dpmo']] as $metric => [$bands, $colors, $label, $category]) {
            $metricDialogs .= '<dialog id="'.$metric.'-formula-dialog" class="perf-style-3398e2afe4"><h3>'.$label.' scoring formula and colours</h3><p class="muted">Minimum thresholds are evaluated highest first (CC uses a strict &gt; 99.8% condition). Below the lowest threshold: 0 points unless a separate below-value rule matches. Colours are independently configurable.</p>';
            if ($full) $metricDialogs .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="metric_action" value="'.$metric.'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken($metric.'_formula_rules')->getValue()).'">';
            $metricDialogs .= '<div class="perf-style-2234e7120f"><table><thead><tr><th>Minimum '.$label.(in_array($metric, ['pod','cc'], true) ? ' %' : '').'</th><th>Points / '.$escape((string)$scoreRules[$category]['maximum']).'</th><th>Colour</th></tr></thead><tbody>';
            foreach ($bands as $i => [$minimum, $awarded]) $metricDialogs .= '<tr><td><input type="number" name="metric_threshold['.$i.']" min="0" '.(in_array($metric, ['pod','cc'], true) ? 'max="100" ' : '').'step="any" required value="'.$escape((string)$minimum).'" class="perf-style-1fa9f1aa52"'.(!$full ? ' disabled' : '').'></td><td><input type="number" name="metric_points['.$i.']" min="0" max="'.$escape((string)$scoreRules[$category]['maximum']).'" step="any" required value="'.$escape((string)$awarded).'" class="perf-style-7b463e1d95"'.(!$full ? ' disabled' : '').'></td><td><input type="color" name="condition_color['.$i.']" value="'.$escape((string)$conditionColours[$metric][(string)(float)$minimum]).'" class="perf-style-d9e5ae8c7f"'.(!$full ? ' disabled' : '').'></td></tr>';
            $metricDialogs .= '</tbody></table></div>';
            if ($full) $metricDialogs .= '<div class="metric-formula-actions"><button type="button" class="metric-add">＋ Add row</button><button type="button" class="metric-remove">− Remove last</button><button type="button" class="metric-sort">↕ Sort thresholds</button></div>';
            $low = $metric === 'cdf' ? $colors['green_below'] : ($metric !== 'dpmo' ? $colors['red_below'] : $colors['green_at_or_below']);
            $high = $metric === 'cdf' ? $colors['red_above'] : ($metric !== 'dpmo' ? $colors['green_above'] : $colors['red_above']);
            $metricDialogs .= '<div hidden class="metric-colour-section"><h4 class="perf-style-df671843ff">Conditional colours</h4><p class="muted perf-style-759e41d83d">Set colour thresholds and choose the colours shown in Weekly Reports.</p><div class="metric-colour-preview"><span data-perf-style="background:'.$escape((string)$colors['red_color']).'">Red</span><span data-perf-style="background:'.$escape((string)$colors['amber_color']).'">Amber</span><span data-perf-style="background:'.$escape((string)$colors['green_color']).'">Green</span></div><div class="metric-colour-grid"><label>'.($metric === 'cdf' ? 'Green when points below' : ($metric !== 'dpmo' ? 'Red below (%)' : 'Green at or below')).'<input type="number" name="metric_low" min="0" '.(in_array($metric, ['pod','cc'], true) ? 'max="100" ' : '').'step="any" required value="'.$escape((string)$low).'"'.(!$full ? ' disabled' : '').'></label><label>'.($metric === 'cdf' ? 'Red when points above' : ($metric !== 'dpmo' ? 'Green above (%)' : 'Red above')).'<input type="number" name="metric_high" min="0" '.(in_array($metric, ['pod','cc'], true) ? 'max="100" ' : '').'step="any" required value="'.$escape((string)$high).'"'.(!$full ? ' disabled' : '').'></label></div><div class="metric-colour-grid">';
            foreach (['red' => 'Red', 'amber' => 'Amber', 'green' => 'Green'] as $shade => $shadeLabel) $metricDialogs .= '<label class="metric-swatch-label">'.$shadeLabel.' colour<input type="color" name="metric_'.$shade.'_color" value="'.$escape((string)$colors[$shade.'_color']).'"'.(!$full ? ' disabled' : '').'></label>';
            $metricDialogs .= '</div></div>';
            if ($metric === 'cdf') $metricDialogs .= '<div class="perf-style-3e7c95524c"><label>Below this feedback value (strict &lt;)<input type="number" name="cdf_below" min="0" step="any" value="'.$escape((string)$cdfBelow).'" required></label><label>Points below this value<input type="number" name="cdf_below_points" min="0" max="'.$escape((string)$scoreRules['cdf_dpmo']['maximum']).'" step="any" value="'.$escape((string)$cdfBelowPoints).'" required></label></div>';
            if ($full) $metricDialogs .= '<div class="metric-save-actions"><button type="submit">Save '.$label.' formula and colours</button><button type="button" data-perf-close-nearest-dialog="1">Close</button></div></form>';
            $metricDialogs .= (!$full ? '<button type="button" class="metric-close" data-perf-close-nearest-dialog="1">Close</button>' : '').'</dialog>';
        }
        $metricDialogs .= '<script src="/driver-performance-inline-29b551b2c34e.js"></script>';
        if (in_array($request->query->get('metric_saved'), ['dpmo','pod','cc'], true)) $metricDialogs .= '<p class="perf-style-9cdf81d40c">'.strtoupper((string)$request->query->get('metric_saved')).' formula saved.</p>';
        if ($full) {
            $rulesTabPanel .= '<div class="perf-style-efa06017dc"><h3>Import report titles</h3><p class="muted">Each import must contain its matching title. Depot prefixes and week numbers can change. Matching is case-insensitive.</p>';
            if ($request->query->get('report_titles_saved') === '1') $rulesTabPanel .= '<p class="perf-style-3f8a2ca06b">Report titles saved.</p>';
            if ($request->query->get('report_titles_error') === '1') $rulesTabPanel .= '<p class="perf-style-2e86f203a8">Each title must be between 1 and 150 characters.</p>';
            $rulesTabPanel .= '<form method="post" action="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('report_titles')->getValue()).'"><input type="hidden" name="rules_action" value="report_titles"><div class="perf-style-8af28ca1c8">';
            foreach (['dsp'=>'DSP Weekly Summary','cc'=>'Contact Compliance (CC)','pod'=>'Photo on Delivery (POD)','dwc'=>'DWC/IADC'] as $key=>$label) {
                $rulesTabPanel .= '<label class="perf-style-c2bc633c54">'.$escape($label).'<input type="text" name="report_titles['.$key.']" value="'.$escape($reportTitles[$key]).'" maxlength="150" required class="perf-style-df3b0e8b48"></label>';
            }
            $rulesTabPanel .= '</div><button type="submit" class="perf-style-e81b63e4cc">Save report titles</button></form></div>';
        }
        $scorecardSafeHtml = \App\Controller\ScorecardRichText::sanitize($scorecardExplanation);
        // Keep rich-text colour formatting without generating CSP-blocked style attributes.
        $scorecardSafeHtml = preg_replace('/\sstyle="([^"]*)"/', ' data-perf-style="$1"', $scorecardSafeHtml);
        $reportPanel .= '<div class="perf-style-efa06017dc"><h3>Report messages and default feedback</h3><p class="muted">Metric-specific improvement messages are edited directly beneath each metric in the Safety and Delivery Quality lists above. This area holds the fallback message and any messages not currently linked to a report metric.</p>';
        if ($request->query->get('scorecard_text_saved') === '1') $reportPanel .= '<p class="perf-style-3f8a2ca06b">Scorecard wording saved.</p>';
        if ($full) $reportPanel .= '<form id="focus-settings-form" method="post" action="/driver-performance?tab=report&amp;week='.$escape($weekStart).'"><input type="hidden" name="_token" value="'.$escape($csrfManager->getToken('scorecard_text')->getValue()).'"><input type="hidden" name="rules_action" value="scorecard_text">';
        $reportPanel .= '<div id="focus-config-list" class="perf-style-0cf3d2f608">';
        foreach ($focusOrder as $key) { $message = $focusMessages[$key]; $reportPanel .= '<div class="focus-config-item" data-focus-key="'.$escape($key).'" data-trigger="'.$escape($focusTriggers[$key]).'" class="perf-style-55499ed3db"><span class="perf-style-6b0e6d96cf">Focus heading ('.$escape($key).')</span><input type="text" name="focus_headings['.$escape($key).']" value="'.$escape($focusHeadings[$key]).'" maxlength="80" required class="perf-style-6fc0fbba27"'.(!$full?' readonly':'').'><textarea name="focus_messages['.$escape($key).']" rows="3" maxlength="1200" class="perf-style-4a517d35ba"'.(!$full?' readonly':'').'>'.$escape($message).'</textarea><label class="perf-style-6cb285c61d">Linked metric <select name="focus_triggers['.$escape($key).']"'.(!$full?' disabled':'').'>'; foreach ($focusTriggerDefaults as $trigger => $_) $reportPanel .= '<option value="'.$escape($trigger).'"'.($focusTriggers[$key] === $trigger?' selected':'').'>'.$escape($trigger).'</option>'; $reportPanel .= '</select></label><input type="hidden" name="focus_order[]" value="'.$escape($key).'">'.'</div>'; }
        $reportPanel .= '</div><div class="perf-style-d6f2af6e0a"><div class="perf-style-fbbd6f2e24">Scorecard explanation (rich text)</div><p class="muted">Format the text below using the toolbar. The same formatting appears on driver scorecards.</p><div class="scorecard-rich-toolbar" role="toolbar" aria-label="Scorecard text formatting"'.(!$full?' hidden':'').'><button type="button" data-command="bold"><b>Bold</b></button><button type="button" data-command="italic"><i>Italic</i></button><button type="button" data-command="underline"><u>Underline</u></button><label>Text colour <input id="scorecard-text-colour" type="color" data-command="foreColor" value="#d91f32"></label><label>Highlight <input id="scorecard-highlight-colour" type="color" data-command="hiliteColor" value="#c7f1ce"></label><button type="button" data-command="insertUnorderedList">Bullets</button><button type="button" data-command="removeFormat">Clear formatting</button></div><div id="scorecard-rich-editor" class="scorecard-rich-editor" contenteditable="'.($full?'true':'false').'" role="textbox" aria-multiline="true" aria-label="Scorecard explanation">'.$scorecardSafeHtml.'</div><textarea name="scorecard_explanation" id="scorecard-rich-value" hidden>'.$escape($scorecardExplanation).'</textarea></div>';
        if ($full) $reportPanel .= '<button type="submit" class="perf-style-66656a96bc">Save scorecard wording</button></form>';
        $reportPanel .= '</div><script src="/driver-performance-inline-1da6ce5c7264.js"></script>';
        $reportPanel .= '</section>';
        $rulesTabPanel .= ($request->query->get('fico_formula_error') === 'duplicate' ? '<p class="perf-style-ebeecaf54f">Duplicate minimum FICO scores. Please use different scores for each row.</p>' : '');
        $rulesTabPanel .= ($request->query->get('fico_formula_saved') === '1' ? '<p class="perf-style-3f8a2ca06b">FICO scoring formula saved.</p>' : '').'<h3>Scoring weights</h3>'.$netradyneRulesPanel.$rulesPanel.$formulaDialog.$dwcDialog.$psbDialog.$ceDialog.$metricDialogs.'</section>';

        $access = $full ? 'Full access' : 'Read only';
        $html = '<!doctype html><html lang="en"><head><link rel="icon" type="image/png" href="/favicon.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Driver Performance · Dulogix</title>'
            .'<link rel="stylesheet" href="/operations-nav.css?v=1"><link rel="stylesheet" href="/driver-performance-page.css?v=1"><link rel="stylesheet" href="/driver-performance-extras.css?v=1"><link rel="stylesheet" href="/driver-performance-inline-classes.css?v=1"><link rel="stylesheet" href="/driver-performance-top-five.css?v=1"><link rel="stylesheet" href="/driver-performance-dsp-import.css?v=1"><link rel="stylesheet" href="/driver-performance-weights.css?v=1"><link rel="stylesheet" href="/driver-performance-sort.css?v=1"><link rel="stylesheet" href="/scorecard-rich-editor.css?v=1"></head><body><header><a href="/"><img src="/dulogix-logo.png" alt="Dulogix"></a>'
            .str_replace('<script>(function(){var toggle=document.querySelector(".du-hamburger"),nav=document.getElementById("du-main-nav");if(toggle&&nav){toggle.addEventListener("click",function(){var open=nav.classList.toggle("du-nav-open");toggle.setAttribute("aria-expanded",String(open));toggle.setAttribute("aria-label",open?"Close menu":"Open menu");});}if(!window.matchMedia("(hover: hover) and (pointer: fine)").matches)return;document.querySelectorAll(".du-menu").forEach(function(menu){menu.addEventListener("mouseenter",function(){menu.open=true;});menu.addEventListener("mouseleave",function(){menu.open=false;});});})();</script>', '', SectionAccess::navigation($this->getUser()))
            .'</header><script src="/operations-nav.js?v=1"></script><main><h1>Driver Performance</h1><p class="muted">Performance Hub · Weekly driver reports</p><span class="pill">'.$access.'</span>'
            .'<div class="weekbar perf-style-67a4836a4a"><a id="week-prev" href="#" aria-label="Previous week" class="perf-style-2acc1f149b">◀</a><strong id="performance-week" class="perf-style-446688fa71"></strong><a id="week-next" href="#" aria-label="Next week" class="perf-style-2acc1f149b">▶</a><label for="week-calendar">Jump to week:</label><input id="week-calendar" type="date" aria-label="Choose a date within the week" class="perf-style-e7442a1f0a"></div>'
            .'<div class="tabs" role="tablist" aria-label="Performance Hub sections"><a class="tab'.($tab === 'weekly' ? ' active' : '').'" href="/driver-performance?tab=weekly&amp;week='.$escape($weekStart).'">Weekly Reports</a><a class="tab'.($tab === 'dsp' ? ' active' : '').'" href="/driver-performance?tab=dsp&amp;week='.$escape($weekStart).'">DSP Weekly Summary</a><a class="tab'.($tab === 'cc' ? ' active' : '').'" href="/driver-performance?tab=cc&amp;week='.$escape($weekStart).'">CC (Contact Compliance)</a><a class="tab'.($tab === 'pod' ? ' active' : '').'" href="/driver-performance?tab=pod&amp;week='.$escape($weekStart).'">POD (Photo on Delivery)</a><a class="tab'.($tab === 'dwc' ? ' active' : '').'" href="/driver-performance?tab=dwc&amp;week='.$escape($weekStart).'">DWC/IADC Report</a><a class="tab'.($tab === 'report' ? ' active' : '').'" href="/driver-performance?tab=report&amp;week='.$escape($weekStart).'">Report</a><a class="tab'.($tab === 'rules' ? ' active' : '').'" href="/driver-performance?tab=rules&amp;week='.$escape($weekStart).'">Rules</a></div>'
            .'<section class="card"'.($tab !== 'weekly' ? ' hidden' : '').'><h2>Weekly Reports</h2><label for="driver-search">Search drivers</label><div class="weekly-search-tools"><input id="driver-search" type="search" placeholder="Search by driver name">'.$topFivePanel.($full ? '<button type="button" id="bonus-export" class="top-five-button">Export Bonus PDF</button>' : '').'</div><p class="muted">Total drivers: '.$count.' · Imported DSP rows: '.$dspCount.'</p><div class="perf-style-5b89f6c914"><table id="performance-drivers" class="perf-style-1d7ebb50ff"><colgroup><col class="perf-style-28c0f6ec6e"><col class="perf-style-76829b4752"><col><col><col><col><col><col><col class="perf-style-1038422181"><col class="perf-style-6d96246bd4"><col></colgroup><thead><tr><th rowspan="2"><label class="driver-select-label driver-select-all-label"><input id="select-all-drivers" type="checkbox" aria-label="Select all visible drivers"><span>Driver name</span></label></th><th rowspan="2" class="perf-style-79409e7012"><div>Rank</div><div>Status</div><div>Total Score</div></th><th colspan="2">Safety</th><th>Deliveries</th><th>Collections</th><th>Concessions</th><th class="perf-style-837ba719d4">Picture on<br>Delivery</th><th title="Contact Compliance" class="perf-style-4e76aad9b4">CC</th><th class="perf-style-15b21a5f4a">Escalations</th><th>Negative Feedback</th></tr><tr><th class="perf-style-bf2fdddf8c">Ementor<br>Score <small>20%</small></th><th class="perf-style-4d1571fa49">Netradyne<br>Violation<br>Deductions</th><th class="perf-style-cc4de8ca47">Delivery<br>Workflow<br>Compliance <small>10%</small></th><th class="perf-style-cc4de8ca47">PSB<br>Collection Fails<br>/100 stops <small>5%</small></th><th class="perf-style-75e3c22ccf">DSC DPMO <small>20%</small></th><th>POD <small>10%</small></th><th class="perf-style-5220487316">CC <small>15%</small></th><th class="perf-style-5220487316">CE <small>15%</small></th><th>Negative Feedback <small>5%</small></th></tr></thead><tbody>'.$weeklyRows.'</tbody></table></div></section>'.$ccPanel.$podPanel.$dspPanel.$dwcPanel.$reportPanel.$rulesTabPanel.'</main><script src="/driver-performance-inline-f0a74207c618.js"></script><script src="/driver-performance-week-nav.js?v=1"></script></body></html>';
        $coachingToken=$escape($csrfManager->getToken('coaching_week_'.$weekStart)->getValue());
        $bonusJs='<input type="hidden" id="performance-coaching-token" value="'.$escape($coachingToken).'"><script src="/driver-performance-bonus-coaching.js?v=1"></script>';
        $html=str_replace('</body>',$bonusJs.'</body>',$html);
        $html = str_replace('</body>', '<script src="/driver-performance-sort.js"></script></body>', $html);
        $html = str_replace('</body>', '<script src="/driver-performance-inline-1c96fefe9b3b.js"></script></body>', $html);
        $html = str_replace('</body>', '<script src="/driver-performance-inline-3fc28e7062be.js"></script></body>', $html);
        $html = str_replace('</body>', '<script src="/driver-performance-inline-9cb9deda832c.js"></script></body>', $html);
        $html = str_replace('</body>', '<script src="/driver-performance-inline-f30cc4ddd50b.js"></script></body>', $html);
        $html = str_replace('</body>', '<script src="/driver-performance-inline-a86fa8fd0ae6.js"></script></body>', $html);
        $html = str_replace('</body>', '<script src="/driver-performance-inline-2142c2c3ae85.js"></script></body>', $html);
        $html = str_replace('</body>', '<script src="/top-five-share.js?v=1"></script></body>', $html);
        $scorecardConfig = json_encode(['focus' => $focusMessages, 'focusHeadings' => $focusHeadings, 'explanation' => $scorecardSafeHtml, 'layout' => $reportLayout, 'focusTriggers' => $focusTriggers, 'focusOrder' => $focusOrder, 'metricFocus' => $metricFocus, 'metricLabels' => $metricLabels, 'safetyOrder' => $safetyOrder], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $html = str_replace('</body>', '<script src="/scorecard-rich-editor.js?v=5"></script></body>', $html);
        $html = str_replace('</body>', '<div id="driver-scorecard-config-json" hidden>'.$escape($scorecardConfig).'</div><script src="/driver-performance-config-bootstrap.js?v=2"></script></body>', $html);
        $html = str_replace('</body>', '<dialog id="driver-scorecard-dialog" class="driver-scorecard-dialog"><div id="driver-scorecard-content"></div><div class="scorecard-actions"><button type="button" id="driver-scorecard-print">Print / Save PDF</button><button type="button" id="driver-scorecard-share-image">Share image on WhatsApp</button><button type="button" id="driver-scorecard-share">Share PDF on WhatsApp</button><button type="button" id="driver-scorecard-close">Close</button></div></dialog><script src="/driver-scorecard.js?v=20261109-26"></script><script src="/dulogix-image-share.js?v=5"></script><link rel="stylesheet" href="/rota-share.css?v=3"><script src="/rota-share.js?v=16"></script><script src="/vendor-html2canvas.min.js?v=1"></script><div id="scorecard-distribution-config-json" hidden>{"week":'.json_encode($weekStart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR).',"csrf":'.json_encode($csrfManager->getToken('scorecard_distribution')->getValue(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR).'}</div><script src="/driver-performance-distribution-config.js?v=2"></script><script src="/vendor-jspdf.min.js?v=1"></script><script src="/driver-scorecard-share.js?v=27"></script><link rel="stylesheet" href="/driver-scorecard-distribution.css?v=1"><script src="/driver-scorecard-distribution.js?v=13"></script></body>', $html);
        $html = str_replace('</body>', '<script src="/driver-performance-dynamic-styles.js?v=1"></script></body>', $html);
        $html = str_replace('</body>', '<script src="/driver-performance-actions.js?v=1"></script></body>', $html);
        $html = str_replace('</body>', '<script src="/driver-performance-weights.js?v=1"></script></body>', $html);
        return new Response($html);
    }
}
