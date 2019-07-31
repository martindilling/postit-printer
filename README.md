# Post-It Story Printer

A small CLI application to print stories/tasks on Post-It notes.  
For now it only works with Pivotal Tracker, but it should be possible to
extend it later if needed.

Works with Post-It notes in the sizes 76x76 cm and 127x76 cm.

# Installation

Install the package to the global composer, and make sure you have 
composers `bin` folder in your PATH.

```
composer global require martindilling/postit-printer
```

# Usage

It is build with a CLI gui, so just run `postit-printer generate` to start.

# Notes

- Remember to print the pdf in 100% scale.
