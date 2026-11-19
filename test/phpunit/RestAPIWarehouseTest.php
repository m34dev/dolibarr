<?php
/* Copyright (C) 2026		William Mead		<william@m34d.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 *      \file       test/phpunit/RestAPIWarehouseTest.php
 *      \ingroup    test
 *      \brief      PHPUnit test
 *      \remarks    To run this script as CLI:  phpunit filename.php
 */

require_once __DIR__."/AbstractRestAPITest.php";

/**
 * Class for PHPUnit tests
 *
 * @backupGlobals disabled
 * @backupStaticAttributes enabled
 * @remarks	backupGlobals must be disabled to have db,conf,user and lang not erased.
 */
class RestAPIWarehouseTest extends AbstractRestAPITest
{
	/**
	 * setUpBeforeClass
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void
	{
		self::assertTrue(isModEnabled('stock'), 'module stock must be enabled');
		parent::setUpBeforeClass();
	}

	/**
	 *
	 * @param string			$endpoint 	Endpoint - "warehouses/id"
	 * @param string			$test_title	Text to be added to output
	 * @param 'GET'|'POST'		$method		Call Method
	 * @param mixed				$data		Data that will be JSON encoded (POST) or sent as query params (GET)
	 * @param int				$expected_error	Expected error code
	 *
	 * @return mixed	Decoded JSON value returned by the service
	 */
	private function getUrl($endpoint, $test_title = "", $method = "GET", $data = null, $expected_error = 0)
	{
		$params = '';
		$body = null;
		if ($method === 'POST') {
			$body = json_encode($data);
		} elseif (!empty($data)) {
			$params = '?'.http_build_query($data);
		}

		$addheaders = array(
			'Content-Type: application/json', 'Accept: application/json', "DOLAPIKEY: ".$this->api_key
		);

		$url = $this->api_url.'/'.$endpoint.$params;

		$result = getURLContent($url, $method, $body, 1, $addheaders, array('http', 'https'), 2);

		$this->assertEquals(0, $result['curl_error_no'], $test_title." Should not have a curl error");

		$object = json_decode($result['content'], true);

		$dbg_info = PHP_EOL.json_encode($result, JSON_PRETTY_PRINT);

		$this->assertNotNull($object, $test_title." - Parsing of JSON result must not be null ".$dbg_info);

		$result['content'] = $object;
		$dbg_info = PHP_EOL.json_encode($result, JSON_PRETTY_PRINT);

		$resultcode = (empty($object['error']['code']) ? 0 : $object['error']['code']);
		$this->assertEquals($expected_error, $resultcode, $test_title." Error code is ".$resultcode." so not ".$expected_error.$dbg_info);

		return $result;
	}

	/**
	 * Call the API with POST and check the answer is 304 Not Modified, which has no JSON body
	 *
	 * @param string				$endpoint	Endpoint - "warehouses/id/close"
	 * @param string				$test_title	Text to be added to output
	 * @param array<string,mixed>	$data		Data that will be JSON encoded
	 * @return void
	 */
	private function assertPostNotModified($endpoint, $test_title, $data = array())
	{
		$addheaders = array(
			'Content-Type: application/json', 'Accept: application/json', "DOLAPIKEY: ".$this->api_key
		);

		$result = getURLContent($this->api_url.'/'.$endpoint, 'POST', json_encode($data), 1, $addheaders, array('http', 'https'), 2);

		$this->assertEquals(0, $result['curl_error_no'], $test_title." Should not have a curl error");
		$this->assertEquals(304, $result['http_code'], $test_title." HTTP code must be 304".PHP_EOL.json_encode($result, JSON_PRETTY_PRINT));
	}

	/**
	 * testRestWarehouseClose
	 *
	 * @return int	Id of the closed warehouse
	 */
	public function testRestWarehouseClose()
	{
		$test = "Close a warehouse";

		$data = array(
			"label" => "testRestWarehouse".mt_rand(0, 99999999),
			"status" => 1,
		);
		$result = $this->getUrl('warehouses', $test, 'POST', $data);
		$id = (int) $result['content'];
		$this->assertGreaterThan(0, $id, "$test Id of warehouse is not > 0");

		$result = $this->getUrl('warehouses/'.$id.'/close', $test, 'POST', array());
		$this->assertEquals(0, $result['content']['status'] ?? 'missing', "$test Returned warehouse must be closed");

		$result = $this->getUrl('warehouses/'.$id, $test, 'GET');
		$this->assertEquals(0, $result['content']['status'] ?? 'missing', "$test Warehouse must be closed");

		// Closing again does nothing
		$this->assertPostNotModified('warehouses/'.$id.'/close', "$test (already closed)");

		return $id;
	}

	/**
	 * testRestWarehouseOpen
	 *
	 * @depends testRestWarehouseClose
	 *
	 * @param int	$id		Id of the closed warehouse
	 * @return int	Id of the open warehouse
	 */
	public function testRestWarehouseOpen($id)
	{
		$test = "Open a warehouse";

		$result = $this->getUrl('warehouses/'.$id.'/open', $test, 'POST', array());
		$this->assertEquals(1, $result['content']['status'] ?? 'missing', "$test Returned warehouse must be open");

		$result = $this->getUrl('warehouses/'.$id, $test, 'GET');
		$this->assertEquals(1, $result['content']['status'] ?? 'missing', "$test Warehouse must be open");

		// Opening again does nothing
		$this->assertPostNotModified('warehouses/'.$id.'/open', "$test (already open)", array("status" => 1));

		return $id;
	}

	/**
	 * testRestWarehouseOpenInternal
	 *
	 * @depends testRestWarehouseOpen
	 *
	 * @param int	$id		Id of the open warehouse
	 * @return void
	 */
	public function testRestWarehouseOpenInternal($id)
	{
		$test = "Open a warehouse for internal movements only";

		if (getDolGlobalString('ENTREPOT_EXTRA_STATUS')) {
			$this->getUrl('warehouses/'.$id.'/open', $test, 'POST', array("status" => 2));
			$result = $this->getUrl('warehouses/'.$id, $test, 'GET');
			$this->assertEquals(2, $result['content']['status'] ?? 'missing', "$test Warehouse must be open for internal movements only");
		} else {
			$this->getUrl('warehouses/'.$id.'/open', $test, 'POST', array("status" => 2), 400);
			$result = $this->getUrl('warehouses/'.$id, $test, 'GET');
			$this->assertEquals(1, $result['content']['status'] ?? 'missing', "$test Warehouse status must not change");
		}

		// A status that is not an open status is refused
		$this->getUrl('warehouses/'.$id.'/open', "$test (bad status)", 'POST', array("status" => 0), 400);
	}

	/**
	 * testRestWarehouseCloseNotFound
	 *
	 * @return void
	 */
	public function testRestWarehouseCloseNotFound()
	{
		$test = "Close or open a warehouse that does not exist";

		$this->getUrl('warehouses/999999999/close', $test, 'POST', array(), 404);
		$this->getUrl('warehouses/999999999/open', $test, 'POST', array(), 404);
	}
}
