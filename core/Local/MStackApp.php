<?php

namespace Local;

use Nether\Common;
use Nether\Console;

#[Console\Meta\Application('Mod Stack Tool', '0.0.1-dev', Phar: 'mstack.phar')]
class MStackApp
extends Console\Client {

	public string
	$AppRoot;

	public string
	$DataRoot;

	public string
	$SrcRoot;

	public string
	$DestRoot;

	public string
	$ConfigFile;

	public string
	$DeployFile;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	OnReady():
	void {

		$AppRoot = $this->GetOption('AppRoot');

		$this->PreparePaths($AppRoot);
		$this->PrepareFiles();

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	#[Console\Meta\Command('list')]
	public function
	HandleList(?Common\Datastore $Pre=NULL, ?Common\Datastore $Mods=NULL):
	int {

		if(!$Pre || !$Mods) {
			$Pre = $this->IndexModFiles();
			$Mods = $this->ShadowModFiles($Pre);
		}

		$NumTotal = 0;
		$NumFinal = 0;
		$Mod = NULL;
		$ModPath = NULL;
		$ModStyle = NULL;

		$ReportHead = [ 'Mod', 'Files', 'Overwritten' ];
		$ReportBody = [];
		$ReportStyle = [];

		////////

		foreach($Mods->Keys() as $Mod) {
			$NumTotal += $Pre[$Mod]->Count();
			$NumFinal += $Mods[$Mod]->Count();

			$ModStyle = match(TRUE) {
				(!$this->ShouldDeployMod($Mod))
				=> Console\Theme::Muted,

				default
				=> Console\Theme::Default
			};

			$ReportBody[] = [
				$Mod,
				$Mods[$Mod]->Count(),
				($Pre[$Mod]->Count() - $Mods[$Mod]->Count())
			];

			$ReportStyle[] = $ModStyle;

			continue;
		}

		$ReportBody[] = [ 'Total', $NumFinal, ($NumTotal - $NumFinal) ];
		$ReportStyle[] = $this->Theme::Accent;

		$this->PrintTable($ReportHead, $ReportBody, Styles: $ReportStyle);

		return 0;
	}

	#[Console\Meta\Command('deploy')]
	public function
	HandleDeploy():
	int {

		$Pre = $this->IndexModFiles();
		$Mods = $this->ShadowModFiles($Pre);

		$this->HandleList($Pre, $Mods);

		$this->CleanDestDir();
		$this->DeployDestDir($Mods);

		return 0;
	}

	#[Console\Meta\Command('clean')]
	public function
	HandleClean():
	int {

		$this->CleanDestDir();

		return 0;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	PreparePaths(string $AppRoot):
	void {

		$this->AppRoot = $AppRoot;

		////////

		$this->DataRoot = Common\Filesystem\Util::Pathify(
			$AppRoot, 'data'
		);

		if(!is_dir($this->DataRoot))
		Common\Filesystem\Util::MkDir($this->DataRoot);

		if(!is_dir($this->DataRoot))
		throw new Common\Error\DirUnwritable($this->DataRoot);

		////////

		$this->SrcRoot = Common\Filesystem\Util::Pathify(
			$AppRoot, 'mods'
		);

		if(!is_dir($this->SrcRoot))
		Common\Filesystem\Util::MkDir($this->SrcRoot);

		if(!is_dir($this->SrcRoot))
		throw new Common\Error\DirUnwritable($this->SrcRoot);

		////////

		$this->DestRoot = 'D:\Games\Starfield\Content';

		////////

		return;
	}

	protected function
	PrepareFiles():
	void {

		$this->DeployFile = Common\Filesystem\Util::Pathify(
			$this->AppRoot, 'data', 'deploy.index'
		);

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	IndexModFiles():
	Common\Datastore {

		$Files = Common\Filesystem\Indexer::DatastoreFromPath(
			$this->SrcRoot,
			TRUE
		);

		$Output = $this->ReindexModFiles($Files);

		$Output->Each(
			fn(Common\Datastore $ModFiles)
			=> $ModFiles->Sort()
		);

		$Output->SortKeys();

		return $Output;
	}

	protected function
	ReindexModFiles(Common\Datastore $Files):
	Common\Datastore {

		$Output = new Common\Datastore;

		////////

		$Files->Each(function(string $File) use($Output) {

			// cut the path down to the local relative.

			$Local = trim(
				str_replace($this->SrcRoot, '', $File),
				DIRECTORY_SEPARATOR
			);

			// determine the mod folder.

			$Bits = explode(DIRECTORY_SEPARATOR, $Local);
			$Mod = array_shift($Bits);
			$Path = join(DIRECTORY_SEPARATOR, $Bits);

			////////

			if(!$Output[$Mod])
			$Output[$Mod] = new Common\Datastore;

			$Output[$Mod]->Push($Path);

			return;
		});

		////////

		return $Output;
	}

	protected function
	ShadowModFiles(Common\Datastore $Mods):
	Common\Datastore {

		// generate a list of files where mods that contain the same
		// file only pull from the mod with highest priority.

		$Output = new Common\Datastore;
		$Seen = new Common\Datastore;
		$Mod = NULL;
		$Files = NULL;

		////////

		foreach($Mods->Mirror() as $Mod=> $Files) {
			foreach($Files as $File) {
				if($Seen->HasKey($File))
				continue;

				if(!$Output->HasKey($Mod))
				$Output[$Mod] = new Common\Datastore;

				$Output[$Mod]->Push($File);
				$Seen->Shove($File, TRUE);
			}
		}

		$Output->Reverse();

		return $Output;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	FetchIndexFile():
	Common\Datastore {

		if(!is_file($this->DeployFile))
		touch($this->DeployFile);

		if(!is_file($this->DeployFile))
		throw new Common\Error\FileUnreadable($this->DeployFile);

		$Output = Common\Datastore::FromArray(file(
			$this->DeployFile,
			FILE_IGNORE_NEW_LINES
		));

		return $Output;
	}

	protected function
	ReadyIndexFile(bool $Blank=FALSE):
	mixed {

		if($Blank)
		file_put_contents($this->DeployFile, '');

		////////

		$FP = fopen($this->DeployFile, 'a');

		if(!$FP)
		throw new Common\Error\FileUnwritable($this->DeployFile);

		////////

		return $FP;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	CleanDestDir():
	void {

		$Files = $this->FetchIndexFile();
		$Remain = new Common\Datastore;

		$this->PrintStatus(sprintf(
			'%d files to clean',
			$Files->Count()
		));

		$Files->Each(function(string $File) use($Remain) {
			$Path = Common\Filesystem\Util::Pathify(
				$this->DestRoot, $File
			);

			if(file_exists($Path)) {
				unlink($Path);
				return;
			}

			$Remain->Push($File);
			return;
		});

		$this->PrintStatus(sprintf(
			'%d files removed',
			($Files->Count() - $Remain->Count())
		));

		return;
	}

	protected function
	DeployDestDir(Common\Datastore $Mods):
	void {

		$FPIndex = $this->ReadyIndexFile(TRUE);
		$FileCount = $Mods->Accumulate(0, static::AccumModFileCount(...));
		$Mod = NULL;
		$Files = NULL;
		$File = NULL;

		$Chunk = NULL;
		$Iter = NULL;

		////////

		$this->PrintStatus(sprintf(
			'%d files to deploy',
			$FileCount
		));

		foreach($Mods as $Mod=> $Files) {

			$Chunk = (int)ceil($Files->Count() / 100);
			$Iter = 0;

			if(!$this->ShouldDeployMod($Mod)) {
				$this->PrintStatusMuted(sprintf('Skipping %s.', $Mod));
				continue;
			}

			$this->PrintStatus(sprintf('Deploying %s...', $Mod));
			$this->DrawProgressBar($Iter, $Files->Count());

			foreach($Files as $File) {
				$Iter += 1;

				if(($Iter % $Chunk) === 0)
				$this->DrawProgressBar($Iter, $Files->Count());

				$Origin = Common\Filesystem\Util::Pathify(
					$this->SrcRoot,
					$Mod, $File
				);

				$Symlink = Common\Filesystem\Util::Pathify(
					$this->DestRoot,
					$File
				);

				////////

				$BaseDir = dirname($Symlink);

				if(!is_dir($BaseDir))
				Common\Filesystem\Util::MkDir($BaseDir);

				////////

				// starfield can see symlinks but not read them
				// apparently.

				if(file_exists($Symlink))
				unlink($Symlink);

				//symlink($Origin, $Symlink);

				system(sprintf(
					'mklink /H %s %s >NUL',
					escapeshellarg($Symlink),
					escapeshellarg($Origin)
				));

				fwrite($FPIndex, "{$File}\n");
			}

			$this->DrawProgressBar($Iter, $Files->Count());
			$this->PrintLn('', 2);
		}

		fclose($FPIndex);
		return;
	}

	protected function
	DrawProgressBar(int $Cur, int $Total):
	void {

		$Per = ($Cur / $Total) * 100;

		printf(
			"\r%d of %d (%d%%)",
			$Cur,
			$Total,
			$Per
		);

		return;
	}

	protected function
	ShouldDeployMod(string $Mod):
	bool {

		if(str_ends_with($Mod, '- Off'))
		return FALSE;

		return TRUE;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	static public function
	AccumModFileCount(int $N, Common\Datastore $M):
	int {

		return $N + $M->Count();
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	GetPharFiles():
	Common\Datastore {

		$Index = parent::GetPharFiles();
		$Index->Push('core');

		return $Index;
	}

	protected function
	GetPharFileFilters():
	Common\Datastore {

		$Filters = parent::GetPharFileFilters();

		$Filters->Push(function(string $File) {

			$DS = DIRECTORY_SEPARATOR;

			// dev deps that dont need to be.

			if(str_contains($File, "squizlabs{$DS}"))
			return FALSE;

			if(str_contains($File, "dealerdirect{$DS}"))
			return FALSE;

			if(str_contains($File, "netherphp{$DS}standards"))
			return FALSE;

			// unused deps from Nether\Common that dont need to be.

			if(str_contains($File, "monolog{$DS}"))
			return FALSE;

			if(str_contains($File, "psr{$DS}log"))
			return FALSE;

			// unused deps from Nether\Database that dont need to be.

			if(str_contains($File, "phelium{$DS}"))
			return FALSE;

			if(str_contains($File, "symfony{$DS}console"))
			return FALSE;

			////////

			return TRUE;
		});

		return $Filters;
	}

};
