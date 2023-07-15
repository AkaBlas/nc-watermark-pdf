# nc-watermark-pdf

Simple Python Script to Add Watermarks to PDFs via [NextCloud Workflows](https://github.com/nextcloud/workflow_script).

It implements two ways of adding watermarks to PDFs:

1. Using [PyPDF](https://pypi.org/project/PyPDF/). This is a pure Python solution, which has its benefits, but is also rather slow.
2. Using [markpdf](https://github.com/AkaBlas/markpdf). This is a GO binary, which is much faster, but requires the binary to be installed on the system.

The script runs on Python >= 3.9. Call it as 

```bash
python script.py --event_type %e --file_id %i --actor_user_id %a --owner_user_id %o --nextcloud_relative_path %n --locally_available_file %f --old_nextcloud_relative_file_path %x
```

in the workflow specification.

Paths are currently hard-coded and may need to be adjusted for other setups.