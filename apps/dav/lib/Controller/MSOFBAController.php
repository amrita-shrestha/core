<?php

namespace OCA\DAV\Controller;

use OC\User\Session;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class MSOFBAController extends Controller {

	/**
	 * @NoCSRFRequired
	 * @NoAdminRequired
	 * @UseSession
	 */
	public function success(): Response {
		$session = \OC::$server->getSession();
		$userSession = \OC::$server->getUserSession();
		$request = \OC::$server->getRequest();
		$user = $userSession->getUser();

		$reflection = new \ReflectionClass($session);
		$property = $reflection->getProperty('sessionValues');
		$property->setAccessible(true);
		$sessionValues =  $property->getValue($session);

		# logout
		$userSession->invalidateSessionToken();
		$userSession->setUser(null);
		$userSession->setLoginName(null);
		$userSession->unsetMagicInCookie();
		$session->clear();

		# re-create session
		$session->regenerateId();
		$userSession->createSessionToken($request, $user->getUID(), $user->getUID());

		foreach ($sessionValues as $k => $v) {
			$session->set($k, $v);
		}

		$resp = new Response();
		$resp->setStatus(200);
		return $resp;
	}
}
