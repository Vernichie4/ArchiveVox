FROM archivebox/archivebox:latest

# Switch to root to modify the system files
USER root

# Replace the text in the top navigation bar template
RUN sed -i 's/ArchiveBox/ArchiveVox/g' /venv/lib/python3.13/site-packages/archivebox/templates/core/base.html

# Switch back to the standard archivebox user for security
USER archivebox
