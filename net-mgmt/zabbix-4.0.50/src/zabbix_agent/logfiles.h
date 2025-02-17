/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_LOGFILES_H
#define ZABBIX_LOGFILES_H

#include "zbxregexp.h"
#include "md5.h"

typedef enum
{
	ZBX_LOG_ROTATION_LOGRT = 0,	/* pure rotation model */
	ZBX_LOG_ROTATION_LOGCPT,	/* copy-truncate rotation model */
	ZBX_LOG_ROTATION_REREAD,	/* reread if modification time changes but size does not */
	ZBX_LOG_ROTATION_NO_REREAD	/* don't reread if modification time changes but size does not */
}
zbx_log_rotation_options_t;

struct	st_logfile
{
	char		*filename;
	int		mtime;		/* st_mtime from stat() */
	int		md5size;	/* size of the initial part for which the md5 sum is calculated */
	int		seq;		/* number in processing order */
	int		retry;
	int		incomplete;	/* 0 - the last record ends with a newline, 1 - the last record contains */
					/* no newline at the end */
	int		copy_of;	/* '-1' - the file is not a copy. '0 <= copy_of' - this file is a copy of */
					/* the file with index 'copy_of' in the old log file list. */
	zbx_uint64_t	dev;		/* ID of device containing file */
	zbx_uint64_t	ino_lo;		/* UNIX: inode number. Microsoft Windows: nFileIndexLow or FileId.LowPart */
	zbx_uint64_t	ino_hi;		/* Microsoft Windows: nFileIndexHigh or FileId.HighPart */
	zbx_uint64_t	size;		/* st_size from stat() */
	zbx_uint64_t	processed_size;	/* how far the Zabbix agent has analyzed the file */
	md5_byte_t	md5buf[MD5_DIGEST_SIZE];	/* md5 sum of the initial part of the file */
};

typedef int (*zbx_process_value_func_t)(const char *, unsigned short, const char *, const char *, const char *,
		unsigned char, zbx_uint64_t *, const int *, unsigned long *, const char *, unsigned short *,
		unsigned long *, unsigned char);

void	destroy_logfile_list(struct st_logfile **logfiles, int *logfiles_alloc, int *logfiles_num);

int	process_logrt(unsigned char flags, const char *filename, zbx_uint64_t *lastlogsize, int *mtime,
		zbx_uint64_t *lastlogsize_sent, int *mtime_sent, unsigned char *skip_old_data, int *big_rec,
		int *use_ino, char **err_msg, struct st_logfile **logfiles_old, const int *logfiles_num_old,
		struct st_logfile **logfiles_new, int *logfiles_num_new, const char *encoding,
		zbx_vector_ptr_t *regexps, const char *pattern, const char *output_template, int *p_count, int *s_count,
		zbx_process_value_func_t process_value, const char *server, unsigned short port, const char *hostname,
		const char *key, int *jumped, float max_delay, double *start_time, zbx_uint64_t *processed_bytes,
		zbx_log_rotation_options_t rotation_type);
#endif
