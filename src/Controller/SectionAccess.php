<?php
namespace App\Controller;
use App\Entity\User;
final class SectionAccess {
    public const SECTIONS = ['rota' => 'Rota Management', 'vehicle' => 'Vehicle Management'];
    public static function allowed(?User $user, string $section): bool {
        if (!$user || !isset(self::SECTIONS[$section])) return false;
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) return true;
        return in_array('ROLE_MANAGER', $user->getRoles(), true) && in_array($section, $user->getSections(), true);
    }
    public static function navigation(?User $user): string {
        if (!$user) return '';
        $html = '<nav class="du-nav" aria-label="Main navigation">';
        if (self::allowed($user, 'rota')) $html .= '<details class="du-menu"><summary>Rota Management</summary><div class="du-submenu"><a href="/">Weekly rota</a><a href="/admin/users">Drivers</a></div></details>';
        if (self::allowed($user, 'vehicle')) $html .= '<details class="du-menu"><summary>Vehicle Management</summary><div class="du-submenu"><a href="/vehicles">Vehicle dashboard</a></div></details>';
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) $html .= '<details class="du-menu"><summary>Settings</summary><div class="du-submenu"><a href="/admin/users">Users</a><a href="/admin/settings">Rota settings</a></div></details>';
        if (in_array('ROLE_DRIVER', $user->getRoles(), true)) $html .= '<a href="/driver/availability">My availability</a>';
        return $html.'<a href="/account">My account</a><a href="/logout">Sign out</a></nav><script>(function(){if(!window.matchMedia("(hover: hover) and (pointer: fine)").matches)return;document.querySelectorAll(".du-menu").forEach(function(menu){menu.addEventListener("mouseenter",function(){menu.open=true;});menu.addEventListener("mouseleave",function(){menu.open=false;});});})();</script>';
    }
    public const CSS = '<style>header{display:flex;align-items:center;gap:24px;min-height:66px;flex-wrap:wrap}header>a{display:inline-flex;align-items:center;flex-shrink:0;margin:0}header>a img{height:42px;margin:0}.du-nav{display:flex;align-items:center;gap:22px;flex:1;flex-wrap:wrap}.du-nav a,.du-nav summary{color:#fff!important;font-size:14px;text-decoration:none;cursor:pointer;margin:0}.du-nav>a:nth-last-child(2){margin-left:auto}.du-menu{position:relative}@media(hover:hover) and (pointer:fine){.du-menu:hover .du-submenu,.du-menu:focus-within .du-submenu{display:grid}.du-menu:not(:hover):not([open]) .du-submenu:not(:focus-within){display:none}}.du-menu summary{padding:10px 4px;list-style:none}.du-menu summary::after{content:" ▾";font-size:12px}.du-menu summary::-webkit-details-marker{display:none}.du-submenu{display:none;position:absolute;top:100%;left:0;z-index:100;background:#173b60;min-width:195px;padding:12px;box-shadow:0 5px 15px #0003;border-radius:6px;gap:12px}.du-menu[open] .du-submenu{display:grid}.du-submenu a{display:block}.du-submenu a:hover,.du-nav>a:hover{text-decoration:underline}@media(max-width:650px){header{gap:10px;padding:10px 14px}.du-nav{gap:14px;width:100%;flex-basis:100%}.du-nav>a:nth-last-child(2){margin-left:0}.du-submenu{position:absolute}}</style>';
}
