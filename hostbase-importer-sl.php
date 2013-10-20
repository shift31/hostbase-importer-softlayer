<?php

require __DIR__ . '/vendor/autoload.php';

use Shift31\HostbaseClient;
use SoftLayer\SLSoapClient;
use SoftLayer\Common\ObjectMask;

$config = parse_ini_file(__DIR__ . '/config.ini');

$HbClient = new HostbaseClient($config['hostbaseUrl']);

$SLClient = SLSoapClient::getClient('SoftLayer_Account', null, $config['apiUsername'], $config['apiKey']);


/*
 * HARDWARE
 */
try {

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
	$SLClient->setObjectMask($objectMask);

	$hardware = $SLClient->getHardware();

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
			$HbClient->store($data);
		} catch (Exception $e) {

			echo $e->getMessage() . PHP_EOL;

			try {
				// update
				echo "updating...\n";
				$HbClient->update($fqdn, $data);
			} catch (Exception $e) {
				echo $e->getMessage() . PHP_EOL;
			}
		}
	}
} catch (Exception $e) {
	echo $e->getMessage() . PHP_EOL;
}


/*
 * VIRTUAL GUESTS
 */
try {
	$objectMask = new ObjectMask();
	$objectMask->virtualGuests->operatingSystem;
	$objectMask->virtualGuests->operatingSystem->passwords;
	$objectMask->virtualGuests->datacenter;
	$objectMask->virtualGuests->serverRoom;
	$SLClient->setObjectMask($objectMask);

	$virtualGuests = $SLClient->getVirtualGuests();

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
			$HbClient->store($data);
		} catch (Exception $e) {

			echo $e->getMessage() . PHP_EOL;

			try {
				// update
				echo "updating...\n";
				$HbClient->update($fqdn, $data);
			} catch (Exception $e) {
				echo $e->getMessage() . PHP_EOL;
			}
		}
	}
} catch (Exception $e) {
	echo $e->getMessage() . PHP_EOL;
}
