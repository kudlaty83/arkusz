#!/usr/bin/env python3
"""Start obrazu z niezmiennym katalogiem aplikacji bez dawnego /var/www/html."""
import argparse,pathlib,shlex,subprocess,tempfile
p=argparse.ArgumentParser();p.add_argument('--engine',default='podman');p.add_argument('--image',default='localhost/arkusz-app:1.2.0');p.add_argument('--runtime');args=p.parse_args()
with tempfile.TemporaryDirectory(prefix='arkusz-workdir-') as tmp:
 root=pathlib.Path(tmp);root.chmod(0o755);(root/'public').mkdir();(root/'public/index.html').write_text('Test startu kontenera')
 code='''if(getcwd()!=="/var/www"||is_dir("/var/www/html"))exit(1);
 if(@file_put_contents("/var/www/write-probe","test")!==false)exit(2);
 if(!is_file("/var/www/public/index.html"))exit(3);
 echo "OK: start bez /var/www/html, katalog roboczy /var/www, aplikacja tylko do odczytu.\n";'''
 command=shlex.split(args.engine)+['run','--rm']
 if args.runtime:command+=['--runtime',args.runtime]
 command+=['--network','none','-v',str(root)+':/var/www:ro,Z','--entrypoint','php',args.image,'-r',code]
 subprocess.run(command,check=True)
