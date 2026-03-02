<?php
declare(strict_types=1);

namespace WarpManager;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;

class Main extends PluginBase {

    private Config $warps;
    private Config $configData;

    public function onEnable(): void {
        @mkdir($this->getDataFolder());
        $this->saveDefaultConfig();

        $this->configData = $this->getConfig();
        $this->warps = new Config($this->getDataFolder() . "warps.yml", Config::YAML);
    }

    private function getMessage(string $key, array $replace = []): string {
        $messages = $this->configData->get("messages", []);
        $msg = $messages[$key] ?? "Message not found.";

        foreach ($replace as $k => $v) {
            $msg = str_replace("{" . $k . "}", (string)$v, $msg);
        }

        return $msg;
    }

    private function isOpOrHasPermission(Player $player, string $permission): bool {
        return $player->hasPermission("warp.admin") || $player->hasPermission($permission);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        $cmd = strtolower($command->getName());

        if (!($sender instanceof Player) && $cmd !== "warps") {
            $sender->sendMessage($this->getMessage("player-only"));
            return true;
        }

        switch ($cmd) {

            case "setwarp":

                if ($sender instanceof Player && !$sender->hasPermission("warp.admin")) {
                    $sender->sendMessage($this->getMessage("no-permission"));
                    return true;
                }

                if (!isset($args[0])) {
                    $sender->sendMessage($this->getMessage("usage-setwarp"));
                    return true;
                }

                $name = strtolower($args[0]);

                $pos = ($sender instanceof Player) ? $sender->getPosition() : new Position(0, 64, 0, Server::getInstance()->getWorldManager()->getDefaultWorld());

                $this->warps->set($name, [
                    "x" => $pos->getX(),
                    "y" => $pos->getY(),
                    "z" => $pos->getZ(),
                    "world" => $pos->getWorld()->getFolderName()
                ]);

                $this->warps->save();

                $sender->sendMessage($this->getMessage("warp-set", ["warp" => $name]));
                return true;

            case "warp":

                if (!isset($args[0])) {
                    $sender->sendMessage($this->getMessage("usage-warp"));
                    return true;
                }

                $name = strtolower($args[0]);

                if (!$this->warps->exists($name)) {
                    $sender->sendMessage($this->getMessage("warp-not-found", ["warp" => $name]));
                    return true;
                }

                if ($sender instanceof Player && !$this->isOpOrHasPermission($sender, "warp." . $name)) {
                    $sender->sendMessage($this->getMessage("no-warp-permission"));
                    return true;
                }

                $data = $this->warps->get($name);

                $world = Server::getInstance()->getWorldManager()->getWorldByName($data["world"]);
                if ($world === null) {
                    Server::getInstance()->getWorldManager()->loadWorld($data["world"]);
                    $world = Server::getInstance()->getWorldManager()->getWorldByName($data["world"]);
                }

                if ($world === null) {
                    $sender->sendMessage("§cWorld not found.");
                    return true;
                }

                if ($sender instanceof Player) {
                    $sender->teleport(new Position(
                        (float)$data["x"],
                        (float)$data["y"],
                        (float)$data["z"],
                        $world
                    ));
                    $sender->sendMessage($this->getMessage("warp-teleported", ["warp" => $name]));
                }

                return true;

            case "delwarp":

                if ($sender instanceof Player && !$sender->hasPermission("warp.admin") && !$sender->hasPermission("warp.delete")) {
                    $sender->sendMessage($this->getMessage("no-permission"));
                    return true;
                }

                if (!isset($args[0])) {
                    $sender->sendMessage($this->getMessage("usage-delwarp"));
                    return true;
                }

                $name = strtolower($args[0]);

                if (!$this->warps->exists($name)) {
                    $sender->sendMessage($this->getMessage("warp-not-found", ["warp" => $name]));
                    return true;
                }

                $this->warps->remove($name);
                $this->warps->save();

                $sender->sendMessage($this->getMessage("warp-deleted", ["warp" => $name]));
                return true;

            case "warps":

                $all = $this->warps->getAll();
                $list = empty($all) ? "None" : implode(", ", array_keys($all));

                $sender->sendMessage($this->getMessage("warp-list", [
                    "warps" => $list
                ]));
                return true;
        }

        return false;
    }
}
