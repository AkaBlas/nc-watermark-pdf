import re
import subprocess
from argparse import ArgumentParser
from pathlib import Path

current_dir = Path(__file__).parent.resolve().absolute()
mark_pdf_path = current_dir / "markpdf"

# # Tested with pypdf==3.12.1
# # Works, but is slow and increases file size significantly
# from pypdf import PdfWriter, PdfReader
# 
# 
# def watermark(
#     content: Path,
#     stamp: Path,
# ) -> None:
#     writer = PdfWriter(clone_from=content)
#     reader = PdfReader(content)
#     watermark_page = PdfReader(stamp).pages[0]
# 
#     for page in writer.pages:
#         page.merge_page(
#             watermark_page,
#             over=False,
#         )
#     writer.add_metadata(reader.metadata)
#     writer.write(content)


# Tested with markpdf v1.0.1
# https://github.com/AkaBlas/markpdf
# Had to be manually compiled from source with the CGO_ENABLED=0 flag
def watermark(content: Path, stamp: Path) -> None:
    subprocess.check_call(
        [
            str(mark_pdf_path),
            str(content),
            str(stamp),
            str(content),
            "-c",
        ]
    )


def rescan_files(
        path: Path,
        unscanned: bool = False,
        shallow: bool = True,
) -> None:
    # For some reason this has to be done for group folders
    effective_path = re.sub(r"__groupfolders/\d+/(.*)", r"\1", str(path))
    commands = ["php", "/home/www/nextcloud/occ", "files:scan", "--path", effective_path]
    if unscanned:
        commands.append("--unscanned")
    if shallow:
        commands.append("--shallow")
    subprocess.check_call(commands)


if __name__ == "__main__":
    parser = ArgumentParser(prog="AkaBlas Watermarker", add_help=True)
    parser.add_argument(
        "--event_type",
        help=(
            r"The event type. One of \OCP\Files::postCreate, \OCP\Files::postWrite or "
            r"\OCP\Files::postRename. Pass as `--event_type %e`."
        ),
        required=True,
        metavar="%e",
    )
    parser.add_argument(
        "--file_id", help="The file id. Pass as `--file_id %i`.", required=True, metavar="%i"
    )
    parser.add_argument(
        "--actor_user_id",
        help="The actor's user id. Pass as `--actor_user_id %a`.",
        required=True,
        metavar="%a",
    )
    parser.add_argument(
        "--owner_user_id",
        help="The owner's user id. Pass as `--owner_user_id %o`.",
        required=True,
        metavar="%o",
    )
    parser.add_argument(
        "--nextcloud_relative_path",
        help="The nextcloud-relative path. Pass as `--nextcloud_relative_path %n`.",
        required=True,
        metavar="%n",
    )
    parser.add_argument(
        "--locally_available_file",
        help="The locally available file. Pass as `--locally_available_file %f`.",
        required=True,
        metavar="%f",
    )
    parser.add_argument(
        "--old_nextcloud_relative_file_path",
        help="The old nextcloud-relative file path (only on rename and copy). Pass as "
        "`--old_nextcloud_relative_file_path %x`.",
        required=True,
        metavar="%x",
    )
    args = parser.parse_args()

    abs_file_path = Path(args.locally_available_file)
    rel_file_path = Path(args.nextcloud_relative_path)

    # sanity check
    if abs_file_path.suffix == ".pdf":
        watermark(
            content=Path(args.locally_available_file),
            stamp=Path(r"/home/www/nextcloud_workflow_scripts/watermark.png"),
        )

        # make nextcloud re-read the file
        rescan_files(path=rel_file_path.parent)

