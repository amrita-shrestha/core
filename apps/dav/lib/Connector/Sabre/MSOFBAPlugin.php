<?php

namespace OCA\DAV\Connector\Sabre;

use OCP\IURLGenerator;
use OCP\IUserSession;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class MSOFBAPlugin extends ServerPlugin {

	/**
	 * Reference to main server object
	 *
	 * @var \Sabre\DAV\Server
	 */
	private $server;

	/**
	 * Reference to logged in user's session
	 *
	 * @var IUserSession
	 */
	private $userSession;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	public function __construct(IUserSession $userSession, IURLGenerator $urlGenerator) {
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * This initializes the plugin.
	 *
	 * This function is called by \Sabre\DAV\Server, after
	 * addPlugin is called.
	 *
	 * This method should set up the required event subscriptions.
	 *
	 * @param \Sabre\DAV\Server $server
	 * @return void
	 */
	public function initialize(\Sabre\DAV\Server $server) {
		$this->server = $server;

		if ($this->IsOFBAAccepted()) {
			$this->server->on('beforeMethod:OPTIONS', [$this, 'httpOptions'], 5);
		}
	}

	public function httpOptions(RequestInterface $request, ResponseInterface $response) {
		# user is logged int -> return 200
		if ($this->userSession->isLoggedIn()) {
			return true;
		}

		$successUrlAbsolute = $this->urlGenerator->linkToRouteAbsolute('dav.MSOFBA.success');

		# not logged in 403 with MS-OFBA headers - https://docs.microsoft.com/en-us/openspecs/sharepoint_protocols/ms-ofba/c2c4baef-c611-4e7b-9a4c-d009e678e3d2
		$loginUrl = $this->urlGenerator->linkToRouteAbsolute(
			'core.login.showLoginForm',
			[
				'redirect_url' => $successUrlAbsolute
			]
		);

		$response->setStatus(403);
		$response->addHeader('X-FORMS_BASED_AUTH_REQUIRED', $loginUrl);
		$response->addHeader('X-FORMS_BASED_AUTH_RETURN_URL', $successUrlAbsolute);
		$response->addHeader('X-FORMS_BASED_AUTH_DIALOG_SIZE', '800x600');
		$response->addHeader('DAV', '1, 2, 3');
		$response->addHeader('MS-Author-Via', 'DAV');

		$this->server->sapi->sendResponse($response);
		return false;
	}

	public function IsOFBAAccepted(): bool {
		$request = $this->server->httpRequest;
		$ofbaAccepted = $request->getHeader('X-FORMS_BASED_AUTH_ACCEPTED');
		if ($ofbaAccepted === 't') {
			return true;
		}

		# only user agent Microsoft Office is of interest
		$userAgent = $request->getHeader('User-Agent');
		if (strpos($userAgent, 'Microsoft Office') >= 0) {
			return true;
		}
		return false;
	}
}
