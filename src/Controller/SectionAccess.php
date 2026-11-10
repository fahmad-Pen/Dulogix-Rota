<?php
namespace App\Controller;
use App\Entity\User;
final class SectionAccess {
    public const SECTIONS = ['rota' => 'Rota Management (Legacy Full Access)', 'rota_read' => 'Rota Management (Read Only)', 'rota_full' => 'Rota Management (Full Access)', 'vehicle' => 'Vehicle Management (Legacy Full Access)', 'vehicle_read' => 'Vehicle Management (Read Only)', 'vehicle_full' => 'Vehicle Management (Full Access)', 'operations' => 'Operations (Legacy Full Access)', 'operations_read' => 'Operations (Read Only)', 'operations_full' => 'Operations (Full Access)', 'performance_read' => 'Driver Performance (Read Only)', 'performance_full' => 'Driver Performance (Full Access)'];
    public static function level(?User $user, string $section): string {
        if (!$user || !in_array($section, ['rota', 'vehicle', 'operations'], true)) return 'none';
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) return 'full';
        if (!in_array('ROLE_MANAGER', $user->getRoles(), true)) return 'none';
        $sections = $user->getSections();
        if (in_array($section.'_full', $sections, true) || in_array($section, $sections, true)) return 'full';
        return in_array($section.'_read', $sections, true) ? 'read' : 'none';
    }
    public static function canEdit(?User $user, string $section): bool {
        return self::level($user, $section) === 'full';
    }
    public static function submittedSections(\Symfony\Component\HttpFoundation\Request $request): array {
        $sections = [];
        foreach (['rota', 'vehicle', 'operations', 'performance'] as $section) {
            $value = (string) $request->request->get($section.'_access', 'none');
            if (in_array($value, [$section.'_read', $section.'_full'], true)) $sections[] = $value;
        }
        return $sections;
    }
    public static function accessSelect(string $section, array $sections): string {
        $label = ['rota'=>'Rota Management', 'vehicle'=>'Vehicle Management', 'operations'=>'Operations'][$section];
        $current = in_array($section.'_full', $sections, true) || in_array($section, $sections, true) ? $section.'_full' : (in_array($section.'_read', $sections, true) ? $section.'_read' : 'none');
        $html = '<label style="display:flex;align-items:center;gap:12px;margin-top:12px">'.$label.' <select name="'.$section.'_access" style="width:auto">';
        foreach (['none'=>'No access', $section.'_read'=>'Read Only', $section.'_full'=>'Full Access'] as $value=>$name) {
            $html .= '<option value="'.$value.'"'.($current===$value?' selected':'').'>'.$name.'</option>';
        }
        return $html.'</select></label>';
    }
    public static function allowed(?User $user, string $section): bool {
        if (!$user || !isset(self::SECTIONS[$section])) return false;
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) return true;
        return in_array($section, ['rota','vehicle','operations'], true) ? self::level($user, $section) !== 'none' : (in_array('ROLE_MANAGER', $user->getRoles(), true) && in_array($section, $user->getSections(), true));
    }
    public static function performanceLevel(?User $user): string {
        if (!$user) return 'none';
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) return 'full';
        if (!in_array('ROLE_MANAGER', $user->getRoles(), true)) return 'none';
        if (in_array('performance_full', $user->getSections(), true)) return 'full';
        return in_array('performance_read', $user->getSections(), true) ? 'read' : 'none';
    }
    public static function navigation(?User $user): string {
        if (!$user) return '';
        $html = '<button type="button" class="du-hamburger" aria-expanded="false" aria-controls="du-main-nav" aria-label="Open menu"><span aria-hidden="true">☰</span><span class="du-hamburger-label">Menu</span></button><nav id="du-main-nav" class="du-nav" aria-label="Main navigation">';
        if (self::allowed($user, 'rota')) $html .= '<details class="du-menu"><summary>Rota Management</summary><div class="du-submenu"><a href="/">Weekly rota</a><a href="/admin/users?view=drivers">Drivers</a></div></details>';
        if (self::allowed($user, 'operations')) $html .= '<details class="du-menu"><summary>Operations</summary><div class="du-submenu"><a href="/operations/report">Operations Report</a></div></details>';
        if (self::performanceLevel($user) !== 'none') $html .= '<details class="du-menu"><summary>Driver Performance</summary><div class="du-submenu"><a href="/driver-performance">Performance Hub</a></div></details>';
        if (self::allowed($user, 'vehicle')) $html .= '<details class="du-menu"><summary>Vehicle Management</summary><div class="du-submenu"><a href="/vehicles">Vehicle dashboard</a></div></details>';
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) $html .= '<details class="du-menu"><summary>Settings</summary><div class="du-submenu"><a href="/admin/users">Users</a><a href="/admin/settings">Rota settings</a><a href="/admin/whatsapp-messages">WhatsApp Quick Messages</a></div></details>';
        if (in_array('ROLE_DRIVER', $user->getRoles(), true)) $html .= '<a href="/driver/daily-check">Daily Shift Check</a><a href="/driver/availability">My Availability</a>';
        return $html.'<a href="/account">My account</a><a href="/logout">Sign out</a></nav><script>(function(){var toggle=document.querySelector(".du-hamburger"),nav=document.getElementById("du-main-nav");if(toggle&&nav){toggle.addEventListener("click",function(){var open=nav.classList.toggle("du-nav-open");toggle.setAttribute("aria-expanded",String(open));toggle.setAttribute("aria-label",open?"Close menu":"Open menu");});}if(!window.matchMedia("(hover: hover) and (pointer: fine)").matches)return;document.querySelectorAll(".du-menu").forEach(function(menu){menu.addEventListener("mouseenter",function(){menu.open=true;});menu.addEventListener("mouseleave",function(){menu.open=false;});});})();</script>';
    }
    public const CSS = '<style>header{display:flex;align-items:center;gap:24px;min-height:66px;flex-wrap:wrap}.du-hamburger{display:none}header>a{display:inline-flex;align-items:center;flex-shrink:0;margin:0}header>a img{height:42px;margin:0}.du-nav{display:flex;align-items:center;gap:22px;flex:1;flex-wrap:wrap}.du-nav a,.du-nav summary{color:#fff!important;font-size:14px;text-decoration:none;cursor:pointer;margin:0}.du-nav>a:nth-last-child(2){margin-left:auto}.du-menu{position:relative}@media(hover:hover) and (pointer:fine){.du-menu:hover .du-submenu,.du-menu:focus-within .du-submenu{display:grid}.du-menu:not(:hover):not([open]) .du-submenu:not(:focus-within){display:none}}.du-menu summary{padding:10px 4px;list-style:none}.du-menu summary::after{content:" ▾";font-size:12px}.du-menu summary::-webkit-details-marker{display:none}.du-submenu{display:none;position:absolute;top:100%;left:0;z-index:100;background:#173b60;min-width:195px;padding:12px;box-shadow:0 5px 15px #0003;border-radius:6px;gap:12px}.du-menu[open] .du-submenu{display:grid}.du-submenu a{display:block}.du-submenu a:hover,.du-nav>a:hover{text-decoration:underline}@media(max-width:650px){header{gap:10px;padding:10px 14px;justify-content:space-between}.du-hamburger{display:flex;align-items:center;gap:8px;margin-left:auto;background:#244a72;color:white;border:1px solid #8aa3bc;border-radius:7px;padding:9px 13px;font-size:16px;cursor:pointer}.du-nav{display:none!important;flex-direction:column;align-items:stretch;gap:0;width:100%;flex-basis:100%;background:#152d49;padding:8px 0}.du-nav.du-nav-open{display:flex!important}.du-nav>a,.du-nav .du-menu>summary{display:block;padding:12px 14px!important;font-size:16px!important}.du-nav>a:nth-last-child(2){margin-left:0}.du-menu{width:100%}.du-submenu,.du-menu[open] .du-submenu{position:static;box-shadow:none;background:#244a72;border-radius:0;padding:5px 18px;min-width:0}.du-submenu a{padding:10px 4px}.du-nav a:focus-visible,.du-hamburger:focus-visible{outline:2px solid white;outline-offset:2px}}</style>';
}
