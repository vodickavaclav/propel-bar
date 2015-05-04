<?php

/**
 * Copyright (c) 2012-2015, Tomáš Kraut <tomas.kraut@matfyz.cz>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 * granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE INCLUDING
 * ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY SPECIAL,
 * DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS,
 * WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH
 * THE USE OR PERFORMANCE OF THIS SOFTWARE.
 *
 */

namespace TKR\Utils;

use Nette\Object;
use \Propel;
use Tracy\Debugger;
use Tracy\Helpers;
use Tracy\IBarPanel;

/**
 * PropelBar - shows SQL queries generated by Propel in Tracy debugger bar
 *
 * @author Tomáš Kraut <tomas.kraut@matfyz.cz>
 */
class PropelBar extends Object implements IBarPanel, \BasicLogger
{

	/**
	 * Logged messages
	 * @var array
	 */
	private $messages = array();

	/**
	 * Log field separator
	 * @var string
	 */
	protected $outer = '|||';

	/**
	 * Log key/value separator
	 * @var string
	 */
	protected $inner = ':::';

	/**
	 *
	 * @var \PropelPDO
	 */
	protected $connection;

	/**
	 * Order of fields in log
	 * @var array
	 */
	protected $fieldIdx = array(
	    'time' => 0,
	    'mem' => 1,
	    'method' => 2,
	    'sql' => 3,
	);

	/**
	 * Skipped source paths - don't generate links to these destinations
	 * @var array
	 */
	protected $libraryPaths;

	/**
	 * Extract field from log row
	 * @param array $row Row to extract
	 * @param string $what Name of field to extraction
	 * @param bool|null $useKey Whether extract key (true), value (false), or whole field (null)
	 * @return string
	 */
	protected function extract($row, $what, $useKey = false)
	{
		$fields = explode($this->outer, $row['message']);
		$idx = $this->fieldIdx[$what];
		if ($idx === null)
			return '';
		$field = $fields[$this->fieldIdx[$what]];
		list($key, $value) = explode($this->inner, $field);
		return $useKey === false ? $value : $key;
	}

	/**
	 * @param array $row
	 * @return float
	 * @internal
	 */
	public function extractTime($row)
	{
		return (float) $this->extract($row, 'time', false);
	}

	/**
	 * @param array $row
	 * @return float
	 * @internal
	 */
	public function extractMem($row)
	{
		return (float) $this->extract($row, 'mem', false);
	}

	/**
	 * @param array $row
	 * @return string
	 * @internal
	 */
	public function extractSql($row)
	{
		return $this->extract($row, 'sql', null);
	}

	/**
	 * @param array $row
	 * @return string
	 * @internal
	 */
	public function extractMethod($row)
	{
		return $this->extract($row, 'method', false);
	}

	/**
	 * @param array $row
	 * @return string
	 * @internal
	 */
	public function extractLink($row)
	{
		return Helpers::editorLink($row['source'][0], $row['source'][1])->class('tracy-PropelBar-source');
	}

	/**
	 * Retrieves total time of execution of SQL queries in ms
	 * @return int
	 */
	public function getTotalTime()
	{
		return 1000 * array_sum(array_map($this->extractTime, $this->messages));
	}

	/**
	 * Returns count of queries
	 * @return int
	 */
	public function getQueryCount()
	{
		return $this->connection->getQueryCount();
	}

	/**
	 * @return string
	 * @internal
	 */
	public function getStyles()
	{
		return <<<HTML
<style type="text/css">
.tracy-PropelBar td.sql {
background-color: white !important;
</style>
HTML;
	}

	/**
	 * Renders tab
	 * @return string
	 */
	public function getTab()
	{
		return '<span title="Propel">'
		    . '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAPOgAADzoBlznbwgAAAnxJREFUOI2VkV1IU3EYxt/3eI6ec+a0bWxt0o4es5Z9EH4UURp5EwUVEUrRhyEW6E0RQRRBXnTVVV1WdGN0UZBGBIqKmqWibGlNsVJibeqO6dmOa7qPs49/FyGlbVTP5cvz/Hjf50X4TwWlFhMqfYeT4a8HKU704b8Go/NtJbGFF/XxgL2GRGZMyfgiaLbcbforQJVad6u+V1dUufskxGQEigZEBuj11c3ZW+/XpQWoC53lUW/zjbi/6wSJLwEwpmSGpnCCYowDGWx+D2e704KIiT8AUc+j7aqv7VY8OFoDBIN0blk3xRW0o3ZnB5931r3WjwAAhBCMzD8thNB0bWzRXoFUxmdat/8ly+UNovF4EACAzD4ohfBgNUSlSsjZdw+tTS2/AN8njcvzj3chlevjxfJ3iFXxn2AHA57WMxAar4fQWAXEFgAYiwqbnpVgTulE2uIIea8hcw8byPiBT2RYT8hQNiHDekJGbGES6D3yu5deFXT1ssBOXYSxhmsQGtsAFA/AinOQZXZAZl4faPZ0YW7VhxQdOBiYfX0O/M9vgjpTCGy+E7jN7cDv6IRIySCKVZF02yLx3D4E6sxpiMla4DZ2A7+tE03nJ1cMTufbMiXgPwqEFIsFpY2CIPhXn5BpiANnuY7GC96VoSzLOdPej6fkb55al8u5V1UjaDYXPLFarYGUb1yR2+3WKYq7TpKmLofDQQEAARFBpzP32GyVxywWy3JKgKIo61yukUZJ+nI1Elk2ZGVxSyyb7eB5bT/Pa/v1+qI3giCEU3Zgt7c3+HzSpUQioeV5bYfBYO5gGHqguLjCmyqwVnQymTQbjdZ6vb5oVBTFtG2n0w8h2wSOf5eJwQAAAABJRU5ErkJggg==">'
		    . $this->queryCount . ($this->queryCount ? ' queries / ' . number_format($this->totalTime, 1, '.', ' ') . ' ms' : '')
		    . '</span>';
	}

	/**
	 * Renders panel
	 * @return string
	 */
	public function getPanel()
	{
		return <<<HTML
$this->styles
<h1>Queries: $this->queryCount, time: $this->totalTime ms</h1>
<div class="tracy-inner tracy-PropelBar">
<table>
<tr>
	<th class="time">Time&nbsp;ms</th>
	<th class="sql">SQL</th>
	<th class="mem">Mem&nbsp;MB</th>
	<th class="method">Method</th>
</tr>
$this->rows
</table>
</div>
HTML;
	}

	/**
	 * @return string
	 * @internal
	 */
	public function getRows()
	{
		$self = $this;
		return implode('', array_map(function($message) use ($self) {
				    return '<tr>'
					. '<td class="time">' . 1000 * $self->extractTime($message) . '</td>'
					. '<td class="sql">' . Helpers::dumpSql($self->extractSql($message)) . $self->extractLink($message) . '</td>'
					. '<td class="mem">' . $self->extractMem($message) . '</td>'
					. '<td class="method">' . $self->extractMethod($message) . '</td>'
					. '</tr>';
			    }, $this->messages));
	}

	public function emergency($m)
	{
		$this->log($m, Propel::LOG_EMERG);
	}

	public function alert($m)
	{
		$this->log($m, Propel::LOG_ALERT);
	}

	public function crit($m)
	{
		$this->log($m, Propel::LOG_CRIT);
	}

	public function err($m)
	{
		$this->log($m, Propel::LOG_ERR);
	}

	public function warning($m)
	{
		$this->log($m, Propel::LOG_WARNING);
	}

	public function notice($m)
	{
		$this->log($m, Propel::LOG_NOTICE);
	}

	public function info($m)
	{
		$this->log($m, Propel::LOG_INFO);
	}

	public function debug($m)
	{
		$this->log($m, Propel::LOG_DEBUG);
	}

	public function log($message, $severity = null)
	{

		$source = null;
		foreach (debug_backtrace(FALSE) as $row) {
			if (
			    isset($row['file'])
			    && is_file($row['file'])
			    //not seen in library paths
			    && !array_reduce($this->libraryPaths, function ($seen, $libraryPath) use ($row) {
					return $seen = ($seen || (strpos($row['file'], $libraryPath . DIRECTORY_SEPARATOR) === 0));
				}, false)
			) {
				if (isset($row['function']) && strpos($row['function'], 'call_user_func') === 0)
					continue;
				if (isset($row['class']) && strpos($row['class'], 'Base') === 0) { //it is base class
					continue;
				}
				$source = array($row['file'], (int) $row['line']);
				break;
			}
		}
		$this->messages[] = array(
		    'severity' => $severity,
		    'message' => $message,
		    'source' => $source,
		);
	}

	/**
	 * Register this panel to DebugBar
	 * @param \PropelPDO $connection Instance of Propel connection
	 * @param array|string $libraryPaths Optional list of paths that should be skipped when searching for place of call
	 * @return PropelBar
	 */
	public static function register(\PropelPDO $connection, $libraryPaths = array())
	{
		$panel = new static();
		$bar = Debugger::getBar();
		if ($bar) {
			$bar->addPanel($panel);
		}
		$panel->setConnection($connection);
		$panel->libraryPaths = array_map('realpath', (array) $libraryPaths);
		return $panel;
	}

	/**
	 * Sets connection and initiate it for query logging
	 * @param \PropelPDO $connection
	 */
	protected function setConnection(\PropelPDO $connection)
	{
		$this->connection = $connection;

		if (!$connection instanceof \DebugPDO) {
			$connection->useDebug(true);
		}

		$propelConfig = $connection->getConfiguration(\PropelConfiguration::TYPE_OBJECT);
		$propelConfig->setParameter('debugpdo.logging.details.time.enabled', true);
		$propelConfig->setParameter('debugpdo.logging.details.time.precision', 4);
		$propelConfig->setParameter('debugpdo.logging.details.mem.enabled', true);
		$propelConfig->setParameter('debugpdo.logging.details.method.enabled', true);
		$propelConfig->setParameter('debugpdo.logging.outerglue', $this->outer);
		$propelConfig->setParameter('debugpdo.logging.innerglue', $this->inner);


		$connection->setLogger($this);
	}

}
