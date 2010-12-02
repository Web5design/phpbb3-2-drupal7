<?php
class progressbar {


	public $terminalWidth = null;
	public $message = '';
	public $total = 100;
	public $size = 100;
	public $done = 0;
	public $strLenPrevLine = null;

/**
 * finish method
 *
 * Set to 100% - useful as a last call after a loop
 * if you don't know the exact number of steps it's going to take
 *
 * @return void
 * @access public
 */
	public function finish() {
		if ($this->done < $this->total) {
			$this->set(null, $this->size);
		}
	}

/**
 * Set the message to be used during updates
 *
 * @param string $message ''
 * @return void
 * @access public
 */
	public function message($message = '') {
		$this->message = $message;
	}

/**
 * Increment the progress
 *
 * @return void
 * @access public
 */
	public function next($inc = 1) {
		$this->done += $inc;
		$this->set();
	}

/**
 * Overrides standard shell output to allow /r without /n
 *
 * Outputs a single or multiple messages to stdout. If no parameters
 * are passed outputs just a newline.
 *
 * @param mixed $message A string or a an array of strings to output
 * @param integer $newlines Number of newlines to append
 * @return integer Returns the number of bytes returned from writing to stdout.
 * @access public
 */
	public function out($message = null, $newLines = 0) {
		print $message;
	}

/**
 * Set the values and output
 *
 * @return void
 * @access public
 */
/**
 * set method
 *
 * @param string $done Amount completed
 * @param string $doneSize bar size
 * @return void
 * @access public
 */
	public function set($done = null, $doneSize = null) {
		if ($done) {
			$this->done = min($done, $this->total);
		}
		$this->total = max(1, $this->total);
		$perc = round($this->done / $this->total, 3);
		if ($doneSize === null) {
			$doneSize = floor(min($perc, 1) * $this->size);
		}
		$message = $this->message;
		if ($message) {
			$output = sprintf(
				"%.01f%% %d/%d [%s+%s]",
				$perc * 100,
				$this->done, $this->total,
				str_repeat("=", $doneSize),
				str_repeat(" ", $this->size - $doneSize)
			);
			$width = strlen($output);

			if (strlen($message) > ($this->terminalWidth - $width - 3)) {
				$message = substr($message, 0, ($this->terminalWidth - $width - 4)) . '...';
			}
			$message = str_pad($message, ($this->terminalWidth - $width));
		} 
		$this->out("\r" . $message . $output);
		flush();
	}

/**
 * Start a progress bar
 *
 * @param string $total Total value of the progress bar
 * @return void
 * @access public
 */
	public function start($total, $width=80,$clear = true) {
		$this->total = $total;
		$this->done = 0;
		$this->setTerminalWidth($width);
		if ($clear) {
			$this->out('', 1);
		}
	}

/**
 * setTerminalWidth method
 *
 * Ask the terminal, and default to min 80 chars.
 *
 * @TODO can you get windows to tell you the size of the terminal?
 * @param mixed $width null
 * @return void
 * @access protected
 */
	protected function setTerminalWidth($width = null) {
		if ($width === null) {
			if (DS === '/') {
				$width = `tput cols`;
			}
			if ($width < 80) {
				$width = 80;
			}
		}
		$this->size = min(max(4, $width / 5), $this->size);
		$this->terminalWidth = $width;
	}
}