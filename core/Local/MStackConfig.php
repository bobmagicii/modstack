<?php

namespace Local;

use Nether\Common;
use Nether\Common\Prototype\PropertyInfo;

class MStackConfig
extends Common\Prototype {

	#[Common\Meta\PropertyListable]
	public string
	$DestRoot = '';

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	Write(string $Filename):
	void {

		$Data = [];
		$Props = Common\Meta\PropertyListable::FromClass($this::class);
		$Prop = NULL;
		$Info = NULL;
		$JSON = NULL;

		////////

		foreach($Props as $Prop => $Info) {
			$Data[$Prop] = $this->{$Prop};
			continue;
		}

		$JSON = Common\Filters\Text::Tabbify(
			json_encode($Data, JSON_PRETTY_PRINT)
		);

		Common\Filesystem\Util::TryToWriteFile(
			$Filename,
			$JSON
		);

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	static public function
	FromFile(string $Filename):
	static {

		$Data = Common\Filesystem\Util::TryToReadFileJSON($Filename);
		$Output = new static($Data);

		return $Output;
	}

	static public function
	WriteDefaultFile(string $Filename):
	void {

		$Tmp = new static;
		$Tmp->Write($Filename);

		return;
	}

};
