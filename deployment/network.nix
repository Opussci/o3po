{
  network.description = "Compositionality";

  webserver =
    { deployment.targetEnv = "virtualbox";
      services.httpd.enable = true;
      services.httpd.documentRoot = "/data";
      fileSystems."/data" =
        { fsType = "nfs4";
          device = "fileserver:/"; };
    };

  fileserver =
    { deployment.targetEnv = "virtualbox";
      services.nfs.server.enable = true;
      services.nfs.server.exports = "...";
    };
}
