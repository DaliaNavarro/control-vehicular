<?php
declare(strict_types=1);
function isAdmin(): bool { return ($_SESSION['role']??'')==='admin'; }
function isExternal(): bool { return ($_SESSION['role']??'')==='user'; }
function currentUserId(): int { return isExternal()?(int)($_SESSION['user_id']??0):0; }
function currentUser(): ?array { return isExternal()?row('SELECT * FROM users WHERE id=? AND active=1',[currentUserId()]):null; }
function requireAdmin(): void { if(!isAdmin()) throw new RuntimeException('Esta sección es exclusiva del administrador.'); }
function licenseState(?array $u): array {
 if(!$u)return ['expired'=>false,'soon'=>false,'days'=>null];
 $today=new DateTimeImmutable('today');$exp=new DateTimeImmutable($u['license_expires']);$days=(int)$today->diff($exp)->format('%r%a');
 return ['expired'=>$days<0,'soon'=>$days>=0&&$days<=30,'days'=>$days];
}
function assertLicenseValid(): void { $u=currentUser(); if($u&&licenseState($u)['expired']) throw new RuntimeException('Tu licencia está vencida. Actualiza los datos de conductor antes de registrar una bitácora.'); }
function canEditTrip(array $t): bool { return isAdmin() || (isExternal() && (int)$t['user_id']===currentUserId()); }
function canViewTrip(array $t): bool {
 if(isAdmin())return true;if(!isExternal())return false;if((int)$t['user_id']===currentUserId())return true;
 return (bool)row('SELECT 1 ok FROM trips WHERE user_id=? AND vehicle_id=? LIMIT 1',[currentUserId(),$t['vehicle_id']]);
}
