{
  network.description = "Web server";

  webserver =
    { config, pkgs, ... }:
    {  services.httpd =
         let
          onepressTheme = pkgs.stdenv.mkDerivation {
              name = "onepress-theme";
              src = pkgs.fetchurl {
                url = https://downloads.wordpress.org/theme/onepress.2.0.9.zip;
                sha256 = "1hdc6ryz6w10i48mww8cgn5qkqlbgw83qlpy0ndhdal3pfna2bc8";
              };
              buildInputs = [ pkgs.unzip ];
              installPhase = "mkdir -p $out; cp -R * $out/";
          };

          akismetPlugin = pkgs.stdenv.mkDerivation {
            name = "akismet-plugin";
            src = pkgs.fetchurl {
              url = https://downloads.wordpress.org/plugin/akismet.3.1.zip;
              sha256 = "1wjq2125syrhxhb0zbak8rv7sy7l8m60c13rfjyjbyjwiasalgzf";
            };
            buildInputs = [ pkgs.unzip ];
            installPhase = "mkdir -p $out; cp -R * $out/";
          };

          relevanssiPlugin = pkgs.stdenv.mkDerivation {
            name = "relevanssi-plugin";
            src = pkgs.fetchurl {
              url = https://downloads.wordpress.org/plugin/relevanssi.4.0.11.zip;
              sha256 = "0myz0p1sk3cyki19li0qk7jczyz0cg43b8921hkgccg6l88hi2l5";
            };
            buildInputs = [ pkgs.unzip ];
            installPhase = "mkdir -p $out; cp -R * $out/";
          };

          o3poPlugin = pkgs.stdenv.mkDerivation {
            name = "relevanssi-plugin";
            src = ../o3po/.;
            installPhase = "mkdir -p $out; cp -R * $out/";
          };

         in
         {
          enable = true;
          adminAddr = "admin@compositionality-journal.org";
          documentRoot = "${pkgs.valgrind.doc}/share/doc/valgrind/html";
          extraModules = [
             { name = "php7"; path = "${pkgs.php72}/modules/libphp7.so"; }
          ];
          virtualHosts = [
            {
              hostName = "O3PO-testintance";
              serverAliases = [ "O3PO-testinstance" ];

              extraSubservices =
              [
                {
                  serviceType = "wordpress";
                  dbPassword = "wordpress";
                  wordpressUploads = "/data/uploads";
                  themes = [ onepressTheme ];
                  plugins = [ akismetPlugin relevanssiPlugin o3poPlugin ];
                }
              ];
            }
          ];
        };
       networking.firewall.allowedTCPPorts = [ 80 ];
       services.mysql = {
        enable = true;
        package = pkgs.mysql;
       };
    };
}
