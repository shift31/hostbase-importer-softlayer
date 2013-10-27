<?php

namespace Hostbase;

use Shift31\HostbaseClient;
use SoftLayer\SLSoapClient;
use SoftLayer\Common\ObjectMask;


class SoftlayerImporter {

	protected $hbClient;

	protected $slApiUsername;

	protected $slApiKey;


	/**
	 * @param $config
	 */
	public function __construct($config) {
		$this->hbClient = new HostbaseClient($config['hostbaseUrl']);

		$this->slApiUsername = $config['slApiUsername'];
		$this->slApiKey = $config['slApiKey'];
	}


	/**
	 * @param string    $serviceName
	 * @param int|null  $id
	 *
	 * @return SLSoapClient
	 */
	protected function getSlClient($serviceName = 'SoftLayer_Account', $id = null)
	{
		return SLSoapClient::getClient($serviceName, $id, $this->slApiUsername, $this->slApiKey);
	}


	/**
	 * Import hosts from hardware
	 */
	public function importHardware()
	{
		try {

			$slClient = $this->getSlClient();

			$objectMask = new ObjectMask();
			$objectMask->hardware->operatingSystem;
			$objectMask->hardware->operatingSystem->passwords;
			$objectMask->hardware->datacenter;
			$objectMask->hardware->memoryCapacity;
			$objectMask->hardware->processors;
			$objectMask->hardware->processorCount;
			$objectMask->hardware->powerSupplyCount;
			$objectMask->hardware->pointOfPresenceLocation;
			$objectMask->hardware->serverRoom;
			$objectMask->hardware->rack;
			$objectMask->hardware->virtualHost;
			$objectMask->hardware->virtualizationPlatform;
			$slClient->setObjectMask($objectMask);

			/** @noinspection PhpUndefinedMethodInspection */
			$hardware = $slClient->getHardware();

			foreach ($hardware as $host) {
				$fqdn = $host->fullyQualifiedDomainName;
				$data = array(
					'fqdn' => $fqdn,
					'datacenter' => $host->datacenter->name,
					'serverRoom' => $host->serverRoom->name,
					'rack' => $host->rack->name,
					'memoryCapacity' => $host->memoryCapacity,
					'powerSupplyCount' => $host->powerSupplyCount,
					'processorCount' => $host->processorCount,
					'processorDescription' => $host->processors[0]->hardwareComponentModel->description,
					'processorManufacturer' => $host->processors[0]->hardwareComponentModel->manufacturer,
					'processorName' => $host->processors[0]->hardwareComponentModel->name,
					'processorVersion' => $host->processors[0]->hardwareComponentModel->version,
					'operatingSystem' => $host->operatingSystem->softwareLicense->softwareDescription->longDescription,
					'primaryPrivateIpAddress' => $host->primaryBackendIpAddress,
					'primaryPublicIpAddress' => $host->primaryIpAddress,
				);

				echo "Importing $fqdn...";

				try {
					// add
					echo "adding...\n";
					$this->hbClient->store($data);
				} catch (\Exception $e) {

					echo $e->getMessage() . PHP_EOL;

					try {
						// update
						echo "updating...\n";
						$this->hbClient->update($fqdn, $data);
					} catch (\Exception $e) {
						echo $e->getMessage() . PHP_EOL;
					}
				}
			}
		} catch (\Exception $e) {
			echo $e->getMessage() . PHP_EOL;
		}
	}


	/**
	 * Import hosts from virtual guests (CloudLayer)
	 */
	public function importVirtualGuests()
	{
		try {

			$slClient = $this->getSlClient();

			$objectMask = new ObjectMask();
			$objectMask->virtualGuests->operatingSystem;
			$objectMask->virtualGuests->operatingSystem->passwords;
			$objectMask->virtualGuests->datacenter;
			$objectMask->virtualGuests->serverRoom;
			$slClient->setObjectMask($objectMask);

			/** @noinspection PhpUndefinedMethodInspection */
			$virtualGuests = $slClient->getVirtualGuests();

			foreach ($virtualGuests as $host) {
				$fqdn = $host->fullyQualifiedDomainName;
				$data = array(
					'fqdn' => $fqdn,
					'datacenter' => $host->datacenter->name,
					'serverRoom' => $host->serverRoom->name,
					'memoryCapacity' => $host->maxMemory / 1024,
					'processorCount' => $host->maxCpu,
					'operatingSystem' => $host->operatingSystem->softwareLicense->softwareDescription->longDescription,
					'primaryPrivateIpAddress' => $host->primaryBackendIpAddress,
					'primaryPublicIpAddress' => $host->primaryIpAddress,
				);

				echo "Importing $fqdn...";

				try {
					// add
					echo "adding...\n";
					$this->hbClient->store($data);
				} catch (\Exception $e) {

					echo $e->getMessage() . PHP_EOL;

					try {
						// update
						echo "updating...\n";
						$this->hbClient->update($fqdn, $data);
					} catch (\Exception $e) {
						echo $e->getMessage() . PHP_EOL;
					}
				}
			}
		} catch (\Exception $e) {
			echo $e->getMessage() . PHP_EOL;
		}

	}


	/**
	 * Import Subnets and IP Addresses
	 */
	public function importSubnets()
	{
		$privateSubnets = $this->getSubnets('private');
		$publicSubnets = $this->getSubnets('public');

		$subnets = array_merge($privateSubnets, $publicSubnets);

		foreach ($subnets as $subnet) {
			if ($subnet->subnetType == 'Primary') continue; // skip PRIMARY subnets (these are reserved for hardware)

			$subnetData = array(
				'network'                   => $subnet->networkIdentifier,
				'netmask'                   => $subnet->netmask,
				'gateway'                   => $subnet->gateway,
				'cidr'                      => $subnet->cidr,
				'addressSpace'              => $subnet->addressSpace,
				'broadcastAddress'          => $subnet->broadcastAddress,
				'vlan'                      => $subnet->vlanNumber,
				'softlayerId'               => $subnet->id,
				'softlayerRouterHostname'   => $subnet->routerHostname,
			);

			if (isset($subnet->note)) $subnetData['note'] = trim($subnet->note);

			$subnetKey = "{$subnetData['network']}/{$subnetData['cidr']}";

			echo "Importing $subnetKey...";

			try {
				// add
				echo "adding...\n";
				$this->hbClient->setResource('subnets')->store($subnetData);
			} catch (\Exception $e) {

				echo $e->getMessage() . PHP_EOL;

				try {
					// update
					echo "updating...\n";
					$this->hbClient->setResource('subnets')->update(str_replace('/', '_', $subnetKey), $subnetData);
				} catch (\Exception $e) {
					echo $e->getMessage() . PHP_EOL;
				}
			}


			// import IP addresses
			$subnetIpAddresses = $this->getIpAddresses($subnet->id);

			foreach ($subnetIpAddresses as $subnetIpAddress) {

				// skip reserved IPs
				if ($subnetIpAddress->status == 'Reserved') continue;

				$ipAddress = $subnetIpAddress->ipAddress;

				$ipAddressData = array(
					'subnet'        => $subnetKey,
					'ipAddress'     => $ipAddress,
					'softlayerId'   => $subnetIpAddress->id
				);

				if (isset($subnetIpAddress->note)) $ipAddressData['note'] = trim($subnetIpAddress->note);

				echo "\tImporting IP address $ipAddress...";

				try {
					// add
					echo "adding...\n";
					$this->hbClient->setResource('ipaddresses')->store($ipAddressData);
				} catch (\Exception $e) {

					echo "\t" . $e->getMessage() . PHP_EOL;

					try {
						// update
						echo "\tupdating...\n";
						$this->hbClient->setResource('ipaddresses')->update($ipAddress, $ipAddressData);
					} catch (\Exception $e) {
						echo "\t" . $e->getMessage() . PHP_EOL;
					}
				}
			}
		}
	}

	/**
	 * @param string $type
	 * @return mixed
	 */
	protected function getSubnets($type = 'private')
	{
		$slClient = $this->getSlClient();

		$objectMask = new ObjectMask();

		$errors = array();

		try {
			if ($type == 'private') {
				/** @noinspection PhpUndefinedFieldInspection */
				$objectMask->privateSubnets->networkVlan->primaryRouter;
				$slClient->setObjectMask($objectMask);
				/** @noinspection PhpUndefinedMethodInspection */
				$subnets = $slClient->getPrivateSubnets();
			} else {
				/** @noinspection PhpUndefinedFieldInspection */
				$objectMask->publicSubnets->networkVlan->primaryRouter;
				$slClient->setObjectMask($objectMask);
				/** @noinspection PhpUndefinedMethodInspection */
				$subnets = $slClient->getPublicSubnets();
			}

			// add vlan number and router hostname; remove networkVlan object
			foreach ($subnets as &$subnet) {
				switch ($subnet->subnetType) {
					case 'PRIMARY':
					case 'ADDITIONAL_PRIMARY':
						$subnet->subnetType = 'Primary';
						break;
					case 'SECONDARY':
						$subnet->subnetType = 'Secondary';
						break;
					case 'ROUTED_TO_VLAN':
					case 'SECONDARY_ON_VLAN':
						$subnet->subnetType = 'Portable';
						break;
					case 'STATIC_IP_ROUTED':
						$subnet->subnetType = 'Static';
						break;
					default:
						break;
				}

				$subnet->vlanNumber = $subnet->networkVlan->vlanNumber;
				$subnet->routerHostname = $subnet->networkVlan->primaryRouter->hostname;
				unset($subnet->networkVlan);
			}

		} catch (\Exception $e) {
			$subnets = null;
			$errors[] = $e->getMessage();
		}

		return $subnets;
	}

	/**
	 * @param $subnetId
	 * @return mixed
	 */
	protected function getIpAddresses($subnetId)
	{
		$errors = array();

		try {
			$slClient = $this->getSlClient('SoftLayer_Network_Subnet', $subnetId);

			/** @noinspection PhpUndefinedMethodInspection */
			$ipAddresses = $slClient->getIpAddresses();

			// add netmask and gateway, and prevent recursion
			foreach ($ipAddresses as &$ipAddress) {

				if ($ipAddress->isNetwork == true || $ipAddress->isGateway == true || $ipAddress->isBroadcast == true || $ipAddress->isReserved == true) {
					$ipAddress->status = 'Reserved';
				} else {
					$ipAddress->status = null;
				}

				if ($ipAddress->isGateway == true) {
					$ipAddress->description = 'Gateway';
				} elseif ($ipAddress->isNetwork == true) {
					$ipAddress->description = 'Network';
				} else {
					$ipAddress->description = null;
				}

				$ipAddress->netmask = $ipAddress->subnet->netmask;
				$ipAddress->gateway = $ipAddress->subnet->gateway;
				unset($ipAddress->subnet); // prevents recursion in subsequent JSON encoding
			}
		} catch (\Exception $e) {
			$ipAddresses = null;
			$errors[] = $e->getMessage();
		}


		return $ipAddresses;
	}

}