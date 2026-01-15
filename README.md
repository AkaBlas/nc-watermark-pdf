# nc-watermark-pdf

Simple Scripting to Add Watermarks to PDFs in a NextCloud Instance.
Contains two versions

## PHP Script

To be run as cron job on the NextCloud server.
Works by scanning a hard-coded directory for PDF files, compares against a persisted list of previously scanned files, and adds watermarks to new files.
Checks only the file name, not content or hash.

Adds watermarks using [markpdf](https://github.com/AkaBlas/markpdf). This is a GO binary, which requires the binary to be installed on the system.

Run the script as 

```bash
php watermark.php
```

or as 

```bash
php watermark.php populate
```

to populate the watermark cache initially with existing, already watermarked PDFs.

When running this as cron job, there are some limitations:

* the directory to scan is hard-coded in the script, so when renaming it, the script needs to be adjusted
* when renaming a file in the cloud, it is detected as a new file and gets a new watermark
* if between two runs of the cron job, a watermarked version of file A is deleted and a watermark-free version of file A is uploaded, the script does not detect it as a "new" file and does not add a watermark again

## Python Script

To be run via [NextCloud Workflows](https://github.com/nextcloud/workflow_script), if `shell_exec` is enabled for PHP in the webserver running NextCloud.

It implements two ways of adding watermarks to PDFs:

1. Using [PyPDF](https://pypi.org/project/PyPDF/). This is a pure Python solution, which has its benefits, but is also rather slow.
2. Using [markpdf](https://github.com/AkaBlas/markpdf). This is a GO binary, which is much faster, but requires the binary to be installed on the system.

The script runs on Python >= 3.9. Call it as 

```bash
python script.py --event_type %e --file_id %i --actor_user_id %a --owner_user_id %o --nextcloud_relative_path %n --locally_available_file %f --old_nextcloud_relative_file_path %x
```

in the workflow specification.

Paths are currently hard-coded and may need to be adjusted for other setups.